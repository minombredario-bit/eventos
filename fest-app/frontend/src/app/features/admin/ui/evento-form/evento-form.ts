import {CommonModule} from '@angular/common';
import {
  AfterViewInit,
  ChangeDetectionStrategy,
  Component,
  computed,
  DestroyRef,
  ElementRef,
  HostListener,
  inject,
  OnDestroy,
  signal,
  ViewChild,
} from '@angular/core';
import {
  FormArray,
  FormBuilder,
  FormControl,
  ReactiveFormsModule,
  UntypedFormGroup,
  Validators,
} from '@angular/forms';
import {takeUntilDestroyed} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router} from '@angular/router';
import {finalize, map} from 'rxjs';

import {AuthService} from '../../../../core/auth/auth';
import {MobileHeader} from '../../../shared/components/mobile-header/mobile-header';
import { ConfirmModal } from '../../../shared/components/confirm-modal/confirm-modal';
import {EventosApi} from '../../../eventos/data/eventos.api';
import {
  ActividadEvento,
  ActivityCompatibility,
  EventoActividadFormValue,
  EventoDetalle,
  MealSlot,
} from '../../../eventos/domain/eventos.models';
import {EventoWritePayload} from '../../../eventos/domain/eventos.api.models';

type SubmitMode = 'normal' | 'floating' | 'bottom';

type EventoActividadFormGroup = UntypedFormGroup & {
  controls: {
    uiId: FormControl<string>;
    id: FormControl<string | null>;
    nombre: FormControl<string>;
    descripcion: FormControl<string>;
    tipoActividad: FormControl<string>;
    franjaComida: FormControl<MealSlot>;
    compatibilidadPersona: FormControl<ActivityCompatibility>;
    esDePago: FormControl<boolean>;
    precioBase: FormControl<string>;
    precioInfantil: FormControl<string>;
    precioAdultoInterno: FormControl<string>;
    precioAdultoExterno: FormControl<string>;
    ordenVisualizacion: FormControl<number>;
    activo: FormControl<boolean>;
  };
};

type EventoFormGroup = UntypedFormGroup & {
  controls: {
    titulo: FormControl<string>;
    descripcion: FormControl<string>;
    fechaEvento: FormControl<string>;
    horaInicio: FormControl<string>;
    horaFin: FormControl<string>;
    lugar: FormControl<string>;
    aforo: FormControl<number | null>;
    fechaInicioInscripcion: FormControl<string>;
    fechaFinInscripcion: FormControl<string>;
    tipoEvento: FormControl<string>;
    visible: FormControl<boolean>;
    admitePago: FormControl<boolean>;
    permiteInvitados: FormControl<boolean>;
    estado: FormControl<string>;
    actividades: FormArray<EventoActividadFormGroup>;
  };
};

@Component({
  selector: 'app-admin-evento-form',
  standalone: true,
  imports: [CommonModule, MobileHeader, ReactiveFormsModule, ConfirmModal],
  templateUrl: './evento-form.html',
  styleUrl: './evento-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminEventoForm implements AfterViewInit, OnDestroy {
  @ViewChild('submitAnchor') submitAnchorRef!: ElementRef<HTMLElement>;
  @ViewChild('formEnd') formEndRef!: ElementRef<HTMLElement>;

  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly eventosApi = inject(EventosApi);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  protected readonly submitMode = signal<SubmitMode>('normal');

  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly evento = signal<EventoDetalle | null>(null);
  protected readonly eventoId = signal<string | null>(null);

  protected readonly estadoOptions = [
    {value: 'borrador', label: 'Borrador'},
    {value: 'publicado', label: 'Publicado'},
    {value: 'cerrado', label: 'Cerrado'},
    {value: 'finalizado', label: 'Finalizado'},
    {value: 'cancelado', label: 'Cancelado'},
  ];

  protected readonly tipoActividadOptions = [
    {value: 'adulto', label: 'Adulto'},
    {value: 'infantil', label: 'Infantil'},
    {value: 'especial', label: 'Especial'},
    {value: 'libre', label: 'Libre'},
  ];

  protected readonly franjaOptions = [
    {value: 'almuerzo', label: 'Almuerzo'},
    {value: 'comida', label: 'Comida'},
    {value: 'merienda', label: 'Merienda'},
    {value: 'cena', label: 'Cena'},
  ];

  protected readonly compatibilidadOptions = [
    {value: 'adulto', label: 'Solo adulto'},
    {value: 'infantil', label: 'Solo infantil'},
    {value: 'ambos', label: 'Adulto e infantil'},
  ];

  protected readonly isEditMode = computed(() => Boolean(this.eventoId()));
  protected readonly pageTitle = computed(() =>
    this.isEditMode() ? 'Editar evento' : 'Nuevo evento'
  );
  protected readonly submitLabel = computed(() =>
    this.saving()
      ? this.isEditMode()
        ? 'Guardando...'
        : 'Creando...'
      : this.isEditMode()
        ? 'Guardar cambios'
        : 'Crear evento'
  );

  protected readonly collapsedActividades = signal<Set<number>>(new Set());

  /** Señal que indica si el usuario actual puede borrar actividades/eventos */
  protected readonly canDeleteActividades = computed(() => {
    const roles = this.authService.userSignal()?.roles ?? [];
    return Array.isArray(roles) && (roles.includes('ROLE_ADMIN_ENTIDAD') || roles.includes('ROLE_SUPERADMIN'));
  });

  /** Estado para el modal de confirmación de borrado */
  protected readonly confirmData = signal<{
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    action?: () => void;
  } | null>(null);

  protected onConfirmModal(confirmed: boolean): void {
    const data = this.confirmData();
    this.confirmData.set(null);
    if (!confirmed || !data?.action) {
      // usuario canceló
      this.saving.set(false);
      return;
    }

    // Ejecutar la acción asociada (por ejemplo la llamada DELETE)
    try {
      data.action();
    } catch (e) {
      // action is expected to handle async errors itself
      this.saving.set(false);
    }
  }

  protected toggleActividad(index: number): void {
    this.collapsedActividades.update(set => {
      const next = new Set(set);
      next.has(index) ? next.delete(index) : next.add(index);
      return next;
    });
  }

  protected isActividadCollapsed(index: number): boolean {
    return this.collapsedActividades().has(index);
  }

  protected readonly form = this.fb.group({
    titulo: this.fb.nonNullable.control('', [Validators.required, Validators.minLength(3)]),
    descripcion: this.fb.nonNullable.control(''),
    fechaEvento: this.fb.nonNullable.control('', [Validators.required]),
    tipoEvento: this.fb.nonNullable.control('comida', [Validators.required]),
    horaInicio: this.fb.nonNullable.control(''),
    horaFin: this.fb.nonNullable.control(''),
    lugar: this.fb.nonNullable.control(''),
    aforo: this.fb.control<number | null>(null),
    fechaInicioInscripcion: this.fb.nonNullable.control(''),
    fechaFinInscripcion: this.fb.nonNullable.control(''),
    visible: this.fb.nonNullable.control(true),
    admitePago: this.fb.nonNullable.control(true),
    permiteInvitados: this.fb.nonNullable.control(true),
    estado: this.fb.nonNullable.control('borrador', [Validators.required]),
    actividades: this.fb.array<EventoActividadFormGroup>([]),
  }) as EventoFormGroup;

  protected get actividades(): FormArray<EventoActividadFormGroup> {
    return this.form.controls.actividades;
  }

  constructor() {
    this.route.paramMap
      .pipe(
        map((params) => params.get('id')),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe((id) => {
        this.errorMessage.set(null);
        this.successMessage.set(null);

        if (!id || id === 'crear') {
          this.eventoId.set(null);
          this.evento.set(null);
          this.resetForCreate();
          this.loading.set(false);
          queueMicrotask(() => this.updateSubmitBarMode());
          return;
        }

        this.eventoId.set(id);
        this.loading.set(true);

        this.eventosApi
          .getEventoAdmin(id)
          .pipe(
            finalize(() => this.loading.set(false)),
            takeUntilDestroyed(this.destroyRef),
          )
          .subscribe({
            next: (evento) => {
              this.evento.set(evento);
              this.patchForm(evento);
              queueMicrotask(() => this.updateSubmitBarMode());
            },
            error: () => {
              this.errorMessage.set('No se pudo cargar el evento.');
            },
          });
      });
  }

  ngAfterViewInit(): void {
    queueMicrotask(() => this.updateSubmitBarMode());
    window.addEventListener('resize', this.onResize, {passive: true});
  }

  ngOnDestroy(): void {
    window.removeEventListener('resize', this.onResize);
  }

  private readonly onResize = (): void => {
    this.updateSubmitBarMode();
  };

  @HostListener('window:scroll')
  onWindowScroll(): void {
    this.updateSubmitBarMode();
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected goBack(): void {
    void this.router.navigate(['/admin/eventos']);
  }

  protected submit(): void {
    this.errorMessage.set(null);
    this.successMessage.set(null);

    if (this.saving()) {
      return;
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.errorMessage.set('Revisa los campos obligatorios antes de guardar.');
      return;
    }

    this.syncActividadOrdenes();

    const value = this.form.getRawValue();

    const payload: EventoWritePayload = {
      titulo: value.titulo.trim(),
      descripcion: value.descripcion.trim(),
      fechaEvento: value.fechaEvento,
      tipoEvento: value.tipoEvento,
      horaInicio: this.normalizeOptionalString(value.horaInicio),
      horaFin: this.normalizeOptionalString(value.horaFin),
      lugar: value.lugar.trim(),
      aforo: value.aforo ?? null,
      fechaInicioInscripcion: this.normalizeOptionalString(value.fechaInicioInscripcion),
      fechaFinInscripcion: this.normalizeOptionalString(value.fechaFinInscripcion),
      visible: value.visible,
      admitePago: value.admitePago,
      permiteInvitados: value.permiteInvitados,
      estado: value.estado,
      requiereVerificacionAcceso: value.requiereVerificacionAcceso,
      actividades: this.buildActividadesPayload(),
    };

    this.saving.set(true);

    const request$ =
      this.isEditMode() && this.eventoId()
        ? this.eventosApi.actualizarEvento(this.eventoId()!, payload)
        : this.eventosApi.crearEvento(payload);

    request$
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (evento) => {
          this.evento.set(evento);
          this.patchForm(evento);
          this.successMessage.set(
            this.isEditMode()
              ? 'Evento actualizado correctamente.'
              : 'Evento creado correctamente.'
          );

          void this.router.navigate(['/admin/eventos', evento.id ?? this.eventoId()]);
        },
        error: () => {
          this.errorMessage.set(
            this.isEditMode()
              ? 'No se pudo actualizar el evento.'
              : 'No se pudo crear el evento.'
          );
        },
      });
  }

  protected addActividad(): void {
    this.actividades.push(this.createActividadFormGroup(this.actividades.length));
    this.syncActividadOrdenes();
    queueMicrotask(() => this.updateSubmitBarMode());
  }

  protected removeActividad(index: number): void {
    if (index < 0 || index >= this.actividades.length) {
      return;
    }

    const control = this.actividades.at(index);
    const actividadId = this.getActividadId(control);

    // Si la actividad ya existe en el servidor (tiene id), pedimos confirmación
    // y delegamos la validación al backend (no hacemos la comprobación previa
    // con /seleccion_participantes porque el servidor se encargará de impedir
    // la eliminación si hay personas apuntadas).
    if (actividadId && this.eventoId()) {
      if (!this.canDeleteActividades()) {
        this.errorMessage.set('No tienes permiso para eliminar actividades.');
        this.saving.set(false);
        return;
      }

      this.errorMessage.set(null);
      // Indicador de operación (reutilizamos saving para señalizar operación en curso)
      this.saving.set(true);

      // Abrir modal de confirmación y, si confirma, intentar borrar en el servidor.
      this.confirmData.set({
        title: 'Eliminar actividad',
        message: '¿Seguro que quieres eliminar esta actividad? Esta acción no se puede deshacer.',
        confirmLabel: 'Eliminar',
        cancelLabel: 'Cancelar',
        action: () => {
          this.eventosApi
            .deleteActividad(actividadId)
            .pipe(takeUntilDestroyed(this.destroyRef))
            .subscribe({
              next: (status) => {
                // Consider successful deletion when backend returns 204 No Content
                if (status === 204 || status === 200) {
                  // Eliminamos del formulario y sincronizamos ordenes
                  this.actividades.removeAt(index);
                  this.syncActividadOrdenes();
                  queueMicrotask(() => this.updateSubmitBarMode());
                  this.collapsedActividades.set(new Set());
                  this.successMessage.set('Actividad eliminada correctamente.');
                } else {
                  // Unexpected status - show a generic success but keep behavior
                  this.successMessage.set('Actividad eliminada.');
                }
                this.saving.set(false);
              },
              error: (err) => {
                // El backend devuelve un error si no es posible borrar (p.ej. hay inscripciones).
                this.errorMessage.set(err?.error?.message ?? 'No se pudo eliminar la actividad en el servidor.');
                this.saving.set(false);
              },
            });
        }
      });

      return;
    }

    // Actividad local (nueva) — eliminamos sin llamadas al backend
    this.actividades.removeAt(index);
    this.syncActividadOrdenes();
    queueMicrotask(() => this.updateSubmitBarMode());
    this.collapsedActividades.set(new Set());
  }

  protected moveActividad(index: number, delta: -1 | 1): void {
    const targetIndex = index + delta;

    if (targetIndex < 0 || targetIndex >= this.actividades.length || targetIndex === index) {
      return;
    }

    const control = this.actividades.at(index);
    this.actividades.removeAt(index);
    this.actividades.insert(targetIndex, control);

    this.syncActividadOrdenes();
    queueMicrotask(() => this.updateSubmitBarMode());
    this.collapsedActividades.set(new Set());
  }

  protected hasPreviousActividad(index: number): boolean {
    return index > 0;
  }

  protected hasNextActividad(index: number): boolean {
    return index < this.actividades.length - 1;
  }

  protected trackActividad(index: number, control: EventoActividadFormGroup): string {
    return this.getActividadUiId(control) || String(index);
  }

  protected actividadTitle(control: EventoActividadFormGroup, index: number): string {
    const nombre = this.getActividadNombre(control);
    return nombre || `Actividad ${index + 1}`;
  }

  protected actividadSubtitle(control: EventoActividadFormGroup): string {
    return `${this.tipoActividadLabel(this.getActividadTipo(control))} · ${this.franjaLabel(this.getActividadFranja(control))} · ${this.compatibilidadLabel(this.getActividadCompatibilidad(control))}`;
  }

  protected franjaLabel(value: string | null | undefined): string {
    const option = this.franjaOptions.find(
      (item) => item.value === (value ?? '').trim().toLowerCase()
    );
    return option?.label ?? 'Franja pendiente';
  }

  protected compatibilidadLabel(value: string | null | undefined): string {
    const option = this.compatibilidadOptions.find(
      (item) => item.value === (value ?? '').trim().toLowerCase()
    );
    return option?.label ?? 'Compatibilidad pendiente';
  }

  protected tipoActividadLabel(value: string | null | undefined): string {
    const option = this.tipoActividadOptions.find(
      (item) => item.value === (value ?? '').trim().toLowerCase()
    );
    return option?.label ?? 'Tipo pendiente';
  }

  private updateSubmitBarMode(): void {
    const anchorEl = this.submitAnchorRef?.nativeElement;
    const endEl = this.formEndRef?.nativeElement;

    if (!anchorEl || !endEl) return;

    const ACTIVATION_THRESHOLD = 0;
    const bottomOffset = this.getBottomOffset();
    const anchorRect = anchorEl.getBoundingClientRect();
    const endRect = endEl.getBoundingClientRect();
    const activationLine = window.innerHeight - bottomOffset - anchorEl.offsetHeight - ACTIVATION_THRESHOLD;
    const releaseLine = window.innerHeight - bottomOffset;

    const next: SubmitMode =
      anchorRect.top > activationLine ? 'normal' :
        endRect.top <= releaseLine ? 'bottom' :
          'floating';

    if (next !== this.submitMode()) {
      this.submitMode.set(next);
    }
  }

  private getBottomOffset(): number {
    const rootStyle = getComputedStyle(document.documentElement);
    const navHeight = parseFloat(rootStyle.getPropertyValue('--bottom-nav-height')) || 0;
    return navHeight + 12;
  }

  private resetForCreate(): void {
    this.form.reset({
      titulo: '',
      descripcion: '',
      fechaEvento: '',
      tipoEvento: 'comida',
      horaInicio: '',
      horaFin: '',
      lugar: '',
      aforo: null,
      fechaInicioInscripcion: '',
      fechaFinInscripcion: '',
      visible: true,
      admitePago: true,
      permiteInvitados: true,
      estado: 'borrador',
    });

    this.populateActividades([]);
  }

  private patchForm(evento: EventoDetalle): void {
    this.form.reset({
      titulo: evento.titulo ?? '',
      descripcion: evento.descripcion ?? '',
      fechaEvento: this.toDateInputValue(evento.fechaEvento),
      tipoEvento: String(evento.tipoEvento ?? 'comida').trim().toLowerCase(),
      horaInicio: this.toTimeInputValue(evento.horaInicio),
      horaFin: this.toTimeInputValue(evento.horaFin),
      lugar: evento.lugar ?? '',
      aforo: this.getEventoAforo(evento),
      fechaInicioInscripcion: this.toDatetimeInputValue(evento.fechaInicioInscripcion),
      fechaFinInscripcion: this.toDatetimeInputValue(
        this.getFechaFinInscripcion(evento)
      ),
      visible: this.getEventoVisible(evento),
      admitePago: this.getEventoAdmitePago(evento),
      permiteInvitados: Boolean(evento.permiteInvitados ?? true),
      estado: evento.estado ?? 'borrador',
    });

    this.populateActividades(Array.isArray(evento.actividades) ? evento.actividades : []);
  }

  private populateActividades(actividades: ActividadEvento[]): void {
    this.actividades.clear();

    const sorted = [...actividades].sort(
      (a, b) => this.toNumber(a.ordenVisualizacion) - this.toNumber(b.ordenVisualizacion)
    );

    sorted.forEach((actividad, index) => {
      this.actividades.push(
        this.createActividadFormGroup(index, this.mapActividadToFormValue(actividad))
      );
    });

    this.syncActividadOrdenes();
    queueMicrotask(() => this.updateSubmitBarMode());
  }

  private createActividadFormGroup(
    ordenVisualizacion: number,
    value: Partial<EventoActividadFormValue> = {},
  ): EventoActividadFormGroup {
    return this.fb.group({
      uiId: this.fb.nonNullable.control(value.uiId ?? this.createUiId()),
      id: this.fb.control<string | null>(value.id ?? null),
      nombre: this.fb.nonNullable.control(value.nombre ?? '', [
        Validators.required,
        Validators.minLength(2),
      ]),
      descripcion: this.fb.nonNullable.control(value.descripcion ?? ''),
      tipoActividad: this.fb.nonNullable.control(value.tipoActividad ?? 'libre', [
        Validators.required,
      ]),
      franjaComida: this.fb.nonNullable.control(value.franjaComida ?? 'comida', [
        Validators.required,
      ]),
      compatibilidadPersona: this.fb.nonNullable.control(
        value.compatibilidadPersona ?? 'ambos',
        [Validators.required]
      ),
      esDePago: this.fb.nonNullable.control(value.esDePago ?? true),
      precioBase: this.fb.nonNullable.control(value.precioBase ?? '0', [Validators.pattern(/^\d+(\.\d{1,2})?$/)]),
      precioInfantil: this.fb.nonNullable.control(value.precioInfantil ?? '0', [Validators.pattern(/^\d+(\.\d{1,2})?$/)]),
      precioAdultoInterno: this.fb.nonNullable.control(value.precioAdultoInterno ?? '0', [Validators.pattern(/^\d+(\.\d{1,2})?$/)]),
      precioAdultoExterno: this.fb.nonNullable.control(value.precioAdultoExterno ?? '0', [Validators.pattern(/^\d+(\.\d{1,2})?$/)]),
      ordenVisualizacion: this.fb.nonNullable.control(
        value.ordenVisualizacion ?? ordenVisualizacion
      ),
      activo: this.fb.nonNullable.control(value.activo ?? true),
    }) as EventoActividadFormGroup;
  }

  private mapActividadToFormValue(actividad: ActividadEvento): Partial<EventoActividadFormValue> {
    return {
      uiId: this.createUiId(),
      id: actividad.id,
      nombre: actividad.nombre ?? '',
      descripcion: actividad.descripcion ?? '',
      tipoActividad: String(actividad.tipoActividad ?? 'libre').trim().toLowerCase(),
      franjaComida: this.normalizeFranja(actividad.franjaComida),
      compatibilidadPersona: this.normalizeCompatibilidad(actividad.compatibilidadPersona),
      esDePago: Boolean(actividad.esDePago),
      precioBase: String(this.toNumber(actividad.precioBase)),
      precioInfantil: this.toNumber(actividad.precioInfantil),
      precioAdultoInterno: this.toNumber(actividad.precioAdultoInterno),
      precioAdultoExterno: this.toNumber(actividad.precioAdultoExterno),
      ordenVisualizacion: this.toNumber(actividad.ordenVisualizacion),
      activo: actividad.activo !== false,
    };
  }

  private buildActividadesPayload(): EventoWritePayload['actividades'] {
    return this.actividades.controls.map((control, index) => {
      const actividadId = this.getActividadId(control);
      return {
        // Si tiene id (viene del backend), enviar el IRI para que API Platform
        // identifique la entidad existente y la actualice en vez de crear una nueva.
        // Si no tiene id (actividad nueva), se omite y el backend la crea.
        ...(actividadId
          ? { 'id': `/api/actividad_eventos/${actividadId}` }
          : {}),
        nombre: this.getActividadNombre(control),
        descripcion: this.normalizeOptionalString(this.getActividadDescripcion(control)),
        tipoActividad: this.getActividadTipo(control),
        franjaComida: this.normalizeFranja(this.getActividadFranja(control)),
        compatibilidadPersona: this.normalizeCompatibilidad(
          this.getActividadCompatibilidad(control)
        ),
        esDePago: this.getActividadEsDePago(control),
        precioBase: String(control.controls.precioBase.value),
        precioInfantil: String(control.controls.precioInfantil.value),
        precioAdultoInterno: String(control.controls.precioAdultoInterno.value),
        precioAdultoExterno: String(control.controls.precioAdultoExterno.value),
        ordenVisualizacion: index,
        activo: this.getActividadActivo(control),
      };
    }) as EventoWritePayload['actividades'];
  }

  private syncActividadOrdenes(): void {
    this.actividades.controls.forEach((control, index) => {
      control.controls.ordenVisualizacion.setValue(index, {emitEvent: false});
    });
  }

  private normalizeFranja(value: string | null | undefined): MealSlot {
    const normalized = (value ?? '').trim().toLowerCase();
    return ['almuerzo', 'comida', 'merienda', 'cena'].includes(normalized)
      ? (normalized as MealSlot)
      : 'comida';
  }

  private normalizeCompatibilidad(value: string | null | undefined): ActivityCompatibility {
    const normalized = (value ?? '').trim().toLowerCase();

    if (normalized === 'adulto' || normalized === 'infantil' || normalized === 'ambos') {
      return normalized as ActivityCompatibility;
    }

    return 'ambos';
  }

  private normalizeOptionalString(value: string | null | undefined): string | undefined {
    const normalized = (value ?? '').trim();
    return normalized.length > 0 ? normalized : undefined;
  }

  private toDateInputValue(value: string | null | undefined): string {
    if (!value) {
      return '';
    }

    return value.includes('T') ? value.split('T')[0] : value.slice(0, 10);
  }

  private toTimeInputValue(value: string | null | undefined): string {
    if (!value) {
      return '';
    }

    // API may return a full datetime with timezone (e.g. "1970-01-01T16:15:00+01:00").
    // Extract the time part after the 'T' and return HH:MM which is suitable for
    // <input type="time"> or the form controls. If the value is already a time
    // string ("16:15" or "16:15:00") return the first 5 chars.
    const afterT = value.includes('T') ? value.split('T')[1] : value;
    // afterT might contain seconds and timezone (+01:00), take first 5 chars (HH:MM)
    return afterT.slice(0, 5);
  }

  private toDatetimeInputValue(value: string | null | undefined): string {
    if (!value) {
      return '';
    }

    return value.includes('T') ? value.slice(0, 16) : `${value}T00:00`;
  }

  private toNumber(value: unknown): number {
    if (typeof value === 'number') {
      return Number.isFinite(value) ? value : 0;
    }

    if (typeof value === 'string') {
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : 0;
    }

    return 0;
  }

  private createUiId(): string {
    return `actividad-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
  }


  private getActividadUiId(control: EventoActividadFormGroup): string {
    return control.controls.uiId.value ?? '';
  }

  private getActividadId(control: EventoActividadFormGroup): string | null {
    const value = control.controls.id.value;
    return typeof value === 'string' && value.trim() ? value.trim() : null;
  }

  private getActividadNombre(control: EventoActividadFormGroup): string {
    return control.controls.nombre.value?.trim() ?? '';
  }

  private getActividadDescripcion(control: EventoActividadFormGroup): string {
    return control.controls.descripcion.value?.trim() ?? '';
  }

  private getActividadTipo(control: EventoActividadFormGroup): string {
    return control.controls.tipoActividad.value?.trim().toLowerCase() ?? 'libre';
  }

  private getActividadFranja(control: EventoActividadFormGroup): string {
    return control.controls.franjaComida.value?.trim().toLowerCase() ?? 'comida';
  }

  private getActividadCompatibilidad(control: EventoActividadFormGroup): string {
    return control.controls.compatibilidadPersona.value?.trim().toLowerCase() ?? 'ambos';
  }

  private getActividadEsDePago(control: EventoActividadFormGroup): boolean {
    return Boolean(control.controls.esDePago.value);
  }


  private getActividadActivo(control: EventoActividadFormGroup): boolean {
    return Boolean(control.controls.activo.value);
  }

  private getEventoAforo(evento: EventoDetalle): number | null {
    const value = (evento as EventoDetalle & { aforo?: number | null }).aforo;
    return typeof value === 'number' ? value : null;
  }

  private getFechaFinInscripcion(evento: EventoDetalle): string | null | undefined {
    const extended = evento as EventoDetalle & {
      fechaFinInscripcion?: string | null;
      fechaLimiteInscripcion?: string | null;
    };

    return extended.fechaFinInscripcion ?? extended.fechaLimiteInscripcion ?? null;
  }

  private getEventoVisible(evento: EventoDetalle): boolean {
    const value = (evento as EventoDetalle & { visible?: boolean }).visible;
    return typeof value === 'boolean' ? value : true;
  }

  private getEventoAdmitePago(evento: EventoDetalle): boolean {
    const value = (evento as EventoDetalle & { admitePago?: boolean }).admitePago;
    return typeof value === 'boolean' ? value : true;
  }
}

// ...existing code...
