import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { AdminApi } from '../../data/admin.api';
import { AdminImportResult, AdminUsuario, AdminUsuariosFiltro, AdminUsuariosPage } from '../../domain/admin.models';

@Component({
  selector: 'app-admin-censo-usuarios',
  standalone: true,
  imports: [MobileHeader, RouterLink],
  templateUrl: './censo-usuarios.html',
  styleUrl: './censo-usuarios.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminCensoUsuarios {
  private static readonly PAGE_SIZE = 10;

  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly adminApi = inject(AdminApi);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly loading = signal(true);
  protected readonly importing = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly importSummary = signal<AdminImportResult | null>(null);
  protected readonly searchTerm = signal('');
  protected readonly filtro = signal<AdminUsuariosFiltro>('censado');
  protected readonly usuariosPage = signal<AdminUsuariosPage>({
    items: [],
    totalItems: 0,
    page: 1,
    itemsPerPage: AdminCensoUsuarios.PAGE_SIZE,
    hasNext: false,
    hasPrevious: false,
  });

  protected readonly usuarios = computed<AdminUsuario[]>(() => this.usuariosPage().items);
  protected readonly totalItems = computed<number>(() => this.usuariosPage().totalItems);
  protected readonly currentPage = computed<number>(() => this.usuariosPage().page);
  protected readonly hasNextPage = computed<boolean>(() => this.usuariosPage().hasNext);
  protected readonly hasPreviousPage = computed<boolean>(() => this.usuariosPage().hasPrevious);

  constructor() {
    this.loadUsuarios();
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected setSearchTerm(value: string): void {
    this.searchTerm.set(value);
    this.loadUsuarios(1);
  }

  protected setFiltro(filtro: AdminUsuariosFiltro): void {
    if (this.filtro() === filtro) {
      return;
    }

    this.filtro.set(filtro);
    this.loadUsuarios(1);
  }

  protected loadNextPage(): void {
    if (!this.hasNextPage()) {
      return;
    }

    this.loadUsuarios(this.currentPage() + 1);
  }

  protected loadPreviousPage(): void {
    if (!this.hasPreviousPage()) {
      return;
    }

    this.loadUsuarios(this.currentPage() - 1);
  }

  protected goToCreateUser(): void {
    void this.router.navigate(['/admin/usuarios/crear']);
  }

  protected onExcelSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file || this.importing()) {
      return;
    }

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
        next: (result) => {
          this.importSummary.set(result);
          this.successMessage.set('Importación finalizada.');
          this.loadUsuarios();
        },
        error: (error: { error?: { error?: string } }) => {
          this.errorMessage.set(error?.error?.error ?? 'No se pudo importar el Excel.');
        },
      });
  }

  protected fullName(usuario: AdminUsuario): string {
    return usuario.nombreCompleto;
  }

  protected estadoLabel(value: string): string {
    const labels: Record<string, string> = {
      pendiente_validacion: 'Pendiente',
      validado: 'Validado',
      rechazado: 'Rechazado',
      bloqueado: 'Bloqueado',
    };

    return labels[value] ?? value;
  }

  protected tipoLabel(value: string): string {
    const labels: Record<string, string> = {
      interno: 'Interno',
      externo: 'Externo',
      invitado: 'Invitado',
    };

    return labels[value] ?? value;
  }

  protected antiguedadLabel(usuario: AdminUsuario): string {
    return usuario.antiguedad === null ? '-' : String(usuario.antiguedad);
  }

  private loadUsuarios(page = 1): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.adminApi
      .getUsuarios({
        search: this.searchTerm(),
        filtro: this.filtro(),
        page,
        itemsPerPage: AdminCensoUsuarios.PAGE_SIZE,
      })
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (usuariosPage) => this.usuariosPage.set(usuariosPage),
        error: (error: { error?: { error?: string } }) => {
          this.usuariosPage.set({
            items: [],
            totalItems: 0,
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

