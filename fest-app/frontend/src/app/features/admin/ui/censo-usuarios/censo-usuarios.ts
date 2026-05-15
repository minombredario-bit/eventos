import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { AdminApi } from '../../data/admin.api';
import { ImportResult, Usuario, UsuariosFiltro, UsuariosPage } from '../../domain/admin.models';
import {TitleCasePipe} from '@angular/common';

export type TipoPersonaFiltro = 'adulto' | 'cadete' | 'infantil' | null;

@Component({
  selector: 'app-admin-censo-usuarios',
  standalone: true,
  imports: [MobileHeader, RouterLink, TitleCasePipe],
  templateUrl: './censo-usuarios.html',
  styleUrls: ['./censo-usuarios.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminCensoUsuarios {
  private static readonly PAGE_SIZE = 10;

  private readonly router      = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly adminApi    = inject(AdminApi);
  private readonly destroyRef  = inject(DestroyRef);

  protected readonly loading       = signal(true);
  protected readonly transitioning = signal(false);
  protected readonly importing     = signal(false);
  protected readonly errorMessage  = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly importSummary = signal<ImportResult | null>(null);

  // ── Filtros ──────────────────────────────────────────────────────────────────
  protected readonly searchTerm           = signal('');
  protected readonly filtro               = signal<UsuariosFiltro>('censado');
  protected readonly tipoPersona          = signal<TipoPersonaFiltro>(null);
  protected readonly fechaNacimientoDesde = signal('');
  protected readonly fechaNacimientoHasta = signal('');

  // ── Descarga de passwords post-importación ───────────────────────────────────
  protected readonly downloadAvailable = signal(false);
  private   readonly passwordsBlob     = signal<Blob | null>(null);

  // ── Cumpleaños ───────────────────────────────────────────────────────────────
  readonly mesNacimientoDesde = signal('');
  readonly mesNacimientoHasta = signal('');

  // ── Paginación ───────────────────────────────────────────────────────────────
  protected readonly usuariosPage = signal<UsuariosPage>({
    items: [],
    totalPages: 0,
    totalItems: 0,
    page: 1,
    itemsPerPage: AdminCensoUsuarios.PAGE_SIZE,
    hasNext: false,
    hasPrevious: false,
  });

  protected readonly usuarios        = computed<Usuario[]>(() => this.usuariosPage().items);
  protected readonly totalItems      = computed<number>(() => this.usuariosPage().totalItems);
  protected readonly currentPage     = computed<number>(() => this.usuariosPage().page);
  protected readonly totalPages      = computed<number>(() => this.usuariosPage().totalPages);
  protected readonly hasNextPage     = computed<boolean>(() => this.usuariosPage().hasNext);
  protected readonly hasPreviousPage = computed<boolean>(() => this.usuariosPage().hasPrevious);

  constructor() {
    this.loadUsuarios(1, true);
  }

  // ── Auth ──────────────────────────────────────────────────────────────────────
  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  // ── Setters de filtro ─────────────────────────────────────────────────────────
  protected setSearchTerm(value: string): void {
    this.searchTerm.set(value);
    this.loadUsuarios(1);
  }

  protected setFiltro(filtro: UsuariosFiltro): void {
    if (this.filtro() === filtro) return;
    this.filtro.set(filtro);
    this.loadUsuarios(1);
  }

  protected setTipoPersona(value: TipoPersonaFiltro): void {
    if (this.tipoPersona() === value) return;
    this.tipoPersona.set(value);
    this.loadUsuarios(1);
  }

  setMesNacimientoDesde(value: string): void {
    this.mesNacimientoDesde.set(value);
    this.loadUsuarios();
  }

  setMesNacimientoHasta(value: string): void {
    this.mesNacimientoHasta.set(value);
    this.loadUsuarios();
  }

  protected clearAllFilters(): void {
    this.searchTerm.set('');
    this.filtro.set('todos');
    this.tipoPersona.set(null);
    this.fechaNacimientoDesde.set('');
    this.fechaNacimientoHasta.set('');
    this.mesNacimientoDesde.set('');
    this.mesNacimientoHasta.set('');
    this.loadUsuarios(1);
  }

  // ── Paginación ────────────────────────────────────────────────────────────────
  protected loadNextPage(): void {
    if (!this.hasNextPage()) return;
    this.loadUsuarios(this.currentPage() + 1);
  }

  protected loadPreviousPage(): void {
    if (!this.hasPreviousPage()) return;
    this.loadUsuarios(this.currentPage() - 1);
  }

  // ── Navegación ────────────────────────────────────────────────────────────────
  protected goToCreateUser(): void {
    void this.router.navigate(['/admin/usuarios/crear']);
  }

  // ── Importación Excel ─────────────────────────────────────────────────────────
  protected onExcelSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (!file || this.importing()) return;

    this.errorMessage.set(null);
    this.successMessage.set(null);
    this.importSummary.set(null);
    this.importing.set(true);

    this.adminApi
      .importarExcel(file)
      .pipe(
        finalize(() => {
          this.importing.set(false);
          input.value = '';
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          const contentType = response.headers.get('content-type') ?? '';

          if (contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
            const blob    = response.body;
            const summary: ImportResult = {
              total:        Number(response.headers.get('X-Import-Total')        ?? 0),
              insertadas:   Number(response.headers.get('X-Import-Insertadas')   ?? 0),
              actualizados: Number(response.headers.get('X-Import-Actualizadas') ?? 0),
              relaciones:   Number(response.headers.get('X-Import-Relaciones')   ?? 0),
              errores:      this.parseErroresHeader(response.headers.get('X-Import-Errores')),
              passwords_excel: null,
            };

            this.importSummary.set(summary);

            if (blob) {
              this.passwordsBlob.set(blob);
              this.downloadAvailable.set(true);
              this.downloadPasswords();
            }

            this.successMessage.set('Importación finalizada. Excel de passwords disponible.');
            this.loadUsuarios(1);
            return;
          }

          const reader = new FileReader();
          reader.onload = () => {
            const result = JSON.parse(reader.result as string) as ImportResult;
            this.importSummary.set(result);
            this.successMessage.set('Importación finalizada.');
            this.loadUsuarios(1);
          };
          if (response.body) reader.readAsText(response.body);
        },
        error: () => this.errorMessage.set('No se pudo importar el Excel.'),
      });
  }

  // ── Exportación Excel ─────────────────────────────────────────────────────────
  protected downloadUsuariosExcel(soloCumples = false): void {
    this.adminApi
      .exportarUsuariosExcel({
        search: this.searchTerm() || undefined,
        filtro: this.filtro(),
        tipoPersona: this.tipoPersona() ?? undefined,
        mesNacimientoDesde: this.mesNacimientoDesde() || undefined,
        mesNacimientoHasta: this.mesNacimientoHasta() || undefined,
        soloCumples,
      })
      .subscribe({
        next: (response) => {
          const blob = response.body;

          if (!blob) {
            this.errorMessage.set('No se pudo generar el Excel.');
            return;
          }

          this.triggerDownload(
            blob,
            soloCumples
              ? 'usuarios_cumples.xlsx'
              : 'usuarios_entidad.xlsx'
          );
        },
        error: () => this.errorMessage.set('No se pudo descargar el Excel.'),
      });
  }

  protected downloadPasswords(): void {
    const blob = this.passwordsBlob();
    if (!blob) return;
    this.triggerDownload(blob, 'usuarios_passwords.xlsx');
  }

  // ── Helpers ───────────────────────────────────────────────────────────────────
  protected fullName(usuario: Usuario): string {
    return usuario.nombreCompleto ?? `${usuario.nombre} ${usuario.apellidos}`;
  }

  private triggerDownload(blob: Blob, fileName: string): void {
    const url  = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href     = url;
    link.download = fileName;
    link.click();
    window.URL.revokeObjectURL(url);
  }

  private parseErroresHeader(value: string | null): string[] {
    if (!value) return [];
    try {
      const parsed = JSON.parse(atob(value));
      return Array.isArray(parsed) ? parsed : Object.values(parsed);
    } catch {
      return [];
    }
  }

  // ── Carga paginada ────────────────────────────────────────────────────────────
  private loadUsuarios(page = 1, isInitial = false): void {
    isInitial ? this.loading.set(true) : this.transitioning.set(true);
    this.errorMessage.set(null);

    this.adminApi
      .getUsuarios({
        search: this.searchTerm() || undefined,
        filtro: this.filtro(),
        tipoPersona: this.tipoPersona() ?? undefined,
        mesNacimientoDesde: this.mesNacimientoDesde() || undefined,
        mesNacimientoHasta: this.mesNacimientoHasta() || undefined,
        page,
        itemsPerPage: AdminCensoUsuarios.PAGE_SIZE,
      })
      .pipe(
        finalize(() => {
          this.loading.set(false);
          this.transitioning.set(false);
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (page) => this.usuariosPage.set(page),
        error: (error: { error?: { error?: string } }) => {
          this.usuariosPage.set({
            items: [],
            totalItems: 0,
            totalPages: 0,
            page: 1,
            itemsPerPage: AdminCensoUsuarios.PAGE_SIZE,
            hasNext: false,
            hasPrevious: false,
          });

          this.errorMessage.set(error?.error?.error ?? 'No se pudo cargar el censo de usuarios.');
        },
      });
  }
}
