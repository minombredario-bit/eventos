import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../../../core/auth/auth';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { AdminApi } from '../../data/admin.api';
import { ImportResult, Usuario, UsuariosFiltro, UsuariosPage } from '../../domain/admin.models';

@Component({
  selector: 'app-admin-censo-usuarios',
  standalone: true,
  imports: [MobileHeader, RouterLink],
  templateUrl: './censo-usuarios.html',
  styleUrls: ['./censo-usuarios.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminCensoUsuarios {
  private static readonly PAGE_SIZE = 10;

  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly adminApi = inject(AdminApi);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly loading = signal(true);
  protected readonly transitioning = signal(false);
  protected readonly importing = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly importSummary = signal<ImportResult | null>(null);
  protected readonly searchTerm = signal('');
  protected readonly filtro = signal<UsuariosFiltro>('censado');
  protected readonly usuariosPage = signal<UsuariosPage>({
    items: [],
    totalPages: 0,
    totalItems: 0,
    page: 1,
    itemsPerPage: AdminCensoUsuarios.PAGE_SIZE,
    hasNext: false,
    hasPrevious: false,
  });

  protected readonly usuarios = computed<Usuario[]>(() => this.usuariosPage().items);
  protected readonly totalItems = computed<number>(() => this.usuariosPage().totalItems);
  protected readonly currentPage = computed<number>(() => this.usuariosPage().page);
  protected readonly totalPages = computed<number>(() => this.usuariosPage().totalPages);
  protected readonly hasNextPage = computed<boolean>(() => this.usuariosPage().hasNext);
  protected readonly hasPreviousPage = computed<boolean>(() => this.usuariosPage().hasPrevious);

  constructor() {
    this.loadUsuarios(1, true);
  }

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected setSearchTerm(value: string): void {
    this.searchTerm.set(value);
    this.loadUsuarios(1);
  }

  protected setFiltro(filtro: UsuariosFiltro): void {
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
          this.loadUsuarios(1, false);
        },
        error: (error: { error?: { error?: string } }) => {
          this.errorMessage.set(error?.error?.error ?? 'No se pudo importar el Excel.');
        },
      });
  }

  protected fullName(usuario: Usuario): string {
    return usuario.nombreCompleto ?? `${usuario.nombre} ${usuario.apellidos}`;
  }

  protected antiguedadLabel(usuario: Usuario): string {
    return usuario.antiguedad === null ? '-' : String(usuario.antiguedad);
  }

  private loadUsuarios(page = 1, isInitial = false): void {
    if (isInitial) {
      this.loading.set(true);
    } else {
      this.transitioning.set(true);  // ← suave, no borra la tabla
    }

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

