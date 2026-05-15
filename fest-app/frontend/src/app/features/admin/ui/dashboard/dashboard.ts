import { CommonModule, NgClass } from '@angular/common';
import {
  ChangeDetectionStrategy, Component, DestroyRef,
  ElementRef, ViewChild, effect, inject, signal, untracked,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { forkJoin, finalize } from 'rxjs';
import { Chart, registerables } from 'chart.js';
import { AuthService } from '../../../../core/auth/auth';
import { formatDate, formatDay, formatMonth } from '../../../../core/utils/date.utils';
import { MobileHeader } from '../../../shared/components/mobile-header/mobile-header';
import { AdminApi } from '../../data/admin.api';
import { EventosApi } from '../../../eventos/data/eventos.api';
import { DashboardStats, DashboardAsistenciaStats } from '../../domain/admin.models';
import { EventoAdminListado } from '../../../eventos/domain/eventos.models';

Chart.register(...registerables);

type ChartMode = 'bar' | 'doughnut' | 'pie';

// Paleta coherente con el tema de la app
const BRAND_ORANGE  = 'rgba(232, 82, 10, 0.85)';
const BRAND_ORANGE2 = 'rgba(200, 61, 0, 0.80)';
const COLOR_BLUE    = 'rgba(59, 130, 246, 0.80)';
const COLOR_PURPLE  = 'rgba(139, 92, 246, 0.80)';
const COLOR_GREEN   = 'rgba(34, 197, 94, 0.80)';
const COLOR_AMBER   = 'rgba(245, 158, 11, 0.85)';
const COLOR_TEAL    = 'rgba(20, 184, 166, 0.80)';
const GRID_COLOR    = 'rgba(0,0,0,0.06)';

@Component({
  selector: 'app-admin-dashboard',
  standalone: true,
  imports: [CommonModule, NgClass, MobileHeader],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminDashboard {
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly adminApi = inject(AdminApi);
  private readonly eventosApi = inject(EventosApi);
  private readonly destroyRef = inject(DestroyRef);

  @ViewChild('franjaChartCanvas')    franjaCanvas?:    ElementRef<HTMLCanvasElement>;
  @ViewChild('tipoChartCanvas')      tipoCanvas?:      ElementRef<HTMLCanvasElement>;
  @ViewChild('actividadChartCanvas') actividadCanvas?: ElementRef<HTMLCanvasElement>;

  private franjaChart:    Chart | null = null;
  private tipoChart:      Chart | null = null;
  private actividadChart: Chart | null = null;

  protected readonly loading            = signal(true);
  protected readonly loadingAsistencia  = signal(false);
  protected readonly errorMessage       = signal<string | null>(null);
  protected readonly downloadingId      = signal<string | null>(null);

  protected readonly stats = signal<DashboardStats>({
    totalCensados: 0,
    totalEventosPublicados: 0,
    proximosEventos: [],
  });

  protected readonly asistenciaStats = signal<DashboardAsistenciaStats | null>(null);

  protected readonly FRANJAS: { key: string; label: string }[] = [
    { key: 'almuerzo', label: 'Almuerzo' },
    { key: 'comida',   label: 'Comida'   },
    { key: 'merienda', label: 'Merienda' },
    { key: 'cena',     label: 'Cena'     },
  ];

  protected readonly TIPOS: { key: 'adulto' | 'infantil' | 'ambos'; label: string }[] = [
    { key: 'adulto',   label: 'Solo adultos'  },
    { key: 'infantil', label: 'Solo infantil' },
    { key: 'ambos',    label: 'Mixtos'        },
  ];

  protected readonly COMPAT_ACTIVIDAD: { key: string; label: string }[] = [
    { key: 'adulto',   label: 'Adulto'   },
    { key: 'cadete',   label: 'Cadete'   },
    { key: 'infantil', label: 'Infantil' },
    { key: 'ambos',    label: 'Ambos'    },
  ];

  protected readonly CHART_MODES: { key: ChartMode; label: string }[] = [
    { key: 'bar',      label: 'Barras'   },
    { key: 'doughnut', label: 'Anillo'   },
    { key: 'pie',      label: 'Sectores' },
  ];

  protected readonly franjaChartMode    = signal<ChartMode>('bar');
  protected readonly tipoChartMode      = signal<ChartMode>('doughnut');
  protected readonly actividadChartMode = signal<ChartMode>('bar');

  protected readonly formatDate  = formatDate;
  protected readonly formatDay   = formatDay;
  protected readonly formatMonth = formatMonth;

  constructor() {
    this.loadStats();
    this.loadAsistenciaStats();

    // Efecto independiente para cada chart → solo se redibuja el que cambió
    effect(() => {
      const stats = this.asistenciaStats();
      const mode = this.franjaChartMode();
      if (!stats) return;
      untracked(() => {
        Promise.resolve().then(() => this.buildFranjaChart(stats, mode));
      });
    });

    effect(() => {
      const stats = this.asistenciaStats();
      const mode = this.tipoChartMode();
      if (!stats) return;
      untracked(() => {
        Promise.resolve().then(() => this.buildTipoChart(stats, mode));
      });
    });

    effect(() => {
      const stats = this.asistenciaStats();
      const mode = this.actividadChartMode();
      if (!stats) return;
      untracked(() => {
        Promise.resolve().then(() => this.buildActividadChart(stats, mode));
      });
    });

    this.destroyRef.onDestroy(() => {
      this.franjaChart?.destroy();
      this.tipoChart?.destroy();
      this.actividadChart?.destroy();
    });
  }

  // ── Navegación ──────────────────────────────────────────────────────────────

  protected logout(): void {
    this.authService.logout();
    void this.router.navigateByUrl('/auth/login');
  }

  protected irAEventos(): void { void this.router.navigate(['/admin/eventos']); }
  protected irACenso():   void { void this.router.navigate(['/admin/censo-usuarios']); }
  protected irAEntidad(): void { void this.router.navigate(['/admin/entidad']); }

  protected irAEvento(id: string): void {
    void this.router.navigate(['/admin/eventos', id]);
  }

  // ── Estado eventos ───────────────────────────────────────────────────────────

  protected statusLabel(evento: EventoAdminListado): string {
    if (evento.inscripcionAbierta) return 'Abierto';
    switch ((evento.estado ?? '').toLowerCase()) {
      case 'publicado':  return 'Publicado';
      case 'cerrado':    return 'Cerrado';
      case 'finalizado': return 'Finalizado';
      case 'cancelado':  return 'Cancelado';
      default:           return 'Borrador';
    }
  }

  protected statusTone(evento: EventoAdminListado): string {
    if (evento.inscripcionAbierta) return 'is-open';
    switch ((evento.estado ?? '').toLowerCase()) {
      case 'publicado':  return 'is-published';
      case 'cerrado':    return 'is-closed';
      case 'finalizado': return 'is-finished';
      case 'cancelado':  return 'is-cancelled';
      default:           return 'is-draft';
    }
  }

  // ── PDF ──────────────────────────────────────────────────────────────────────

  protected descargarParticipantes(evento: EventoAdminListado, event?: Event): void {
    event?.stopPropagation();
    if (this.downloadingId() === evento.id) return;

    this.downloadingId.set(evento.id);
    this.errorMessage.set(null);

    this.eventosApi
      .descargarReportePdf(evento.id)
      .pipe(finalize(() => this.downloadingId.set(null)), takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (blob) => {
          const url  = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = this.buildFileName(evento.titulo, evento.id);
          link.style.display = 'none';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);
        },
        error: () => this.errorMessage.set('No se pudo descargar el listado de participantes.'),
      });
  }

  // ── Carga de datos ───────────────────────────────────────────────────────────

  private loadStats(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    forkJoin({
      censados:          this.adminApi.getUsuariosCount({ filtro: 'censado' }),
      proximosEventos:   this.eventosApi.getProximosEventos(3),
      eventosPublicados: this.eventosApi.getEventosAdminStats({ upcoming: false, limit: 100 }),
    })
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: ({ censados, proximosEventos, eventosPublicados }) => {
          const publicados = eventosPublicados.filter(
            (e) => ['publicado', 'abierto'].includes((e.estado ?? '').toLowerCase()) || e.inscripcionAbierta,
          ).length;
          this.stats.set({ totalCensados: censados, totalEventosPublicados: publicados, proximosEventos });
        },
        error: () => this.errorMessage.set('No se pudieron cargar las estadísticas del panel.'),
      });
  }

  private loadAsistenciaStats(): void {
    this.loadingAsistencia.set(true);

    this.adminApi.getDashboardAsistenciaStats()
      .pipe(
        finalize(() => this.loadingAsistencia.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next:  (data) => this.asistenciaStats.set(data),
        error: () => { /* las stats de asistencia son opcionales */ },
      });
  }

  // ── Chart.js ─────────────────────────────────────────────────────────────────

  protected hasActividadData(stats: DashboardAsistenciaStats): boolean {
    return Object.values(stats.mediaPorActividad ?? {}).some((v) => v != null);
  }

  private buildFranjaChart(stats: DashboardAsistenciaStats, mode: ChartMode): void {
    const canvas = this.franjaCanvas?.nativeElement;
    if (!canvas) return;
    const labels: string[] = [];
    const values: number[] = [];
    for (const franja of this.FRANJAS) {
      const val = stats.mediaPorFranja[franja.key];
      if (val != null) { labels.push(franja.label); values.push(val); }
    }
    this.franjaChart?.destroy();
    this.franjaChart = this.buildChart(
      canvas, mode, labels, values,
      [BRAND_ORANGE, COLOR_BLUE, COLOR_PURPLE, BRAND_ORANGE2],
    );
  }

  private buildTipoChart(stats: DashboardAsistenciaStats, mode: ChartMode): void {
    const canvas = this.tipoCanvas?.nativeElement;
    if (!canvas) return;
    const labels: string[] = [];
    const values: number[] = [];
    for (const tipo of this.TIPOS) {
      const val = stats.mediaPorTipo[tipo.key];
      if (val != null) { labels.push(tipo.label); values.push(val); }
    }
    if (values.length === 0) return;
    this.tipoChart?.destroy();
    this.tipoChart = this.buildChart(
      canvas, mode, labels, values,
      [BRAND_ORANGE, COLOR_BLUE, COLOR_GREEN],
    );
  }

  private buildActividadChart(stats: DashboardAsistenciaStats, mode: ChartMode): void {
    const canvas = this.actividadCanvas?.nativeElement;
    if (!canvas) return;
    const labels: string[] = [];
    const values: number[] = [];
    for (const compat of this.COMPAT_ACTIVIDAD) {
      const val = stats.mediaPorActividad?.[compat.key];
      if (val != null) { labels.push(compat.label); values.push(val); }
    }
    if (values.length === 0) return;
    this.actividadChart?.destroy();
    this.actividadChart = this.buildChart(
      canvas, mode, labels, values,
      [BRAND_ORANGE, COLOR_TEAL, COLOR_BLUE, COLOR_PURPLE],
    );
  }

  /** Construye un Chart.js en función del modo elegido */
  private buildChart(
    canvas: HTMLCanvasElement,
    mode: ChartMode,
    labels: string[],
    values: number[],
    colors: string[],
  ): Chart {
    if (mode === 'bar') {
      return new Chart(canvas, {
        type: 'bar',
        data: {
          labels,
          datasets: [{ label: 'Media asistentes', data: values,
            backgroundColor: colors.slice(0, values.length),
            borderRadius: 8, borderSkipped: false }],
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.x} asistentes de media` } },
          },
          scales: {
            x: { beginAtZero: true, grid: { color: GRID_COLOR }, ticks: { font: { size: 11 } } },
            y: { grid: { display: false }, ticks: { font: { size: 12, weight: 'bold' } } },
          },
        },
      });
    }
    // doughnut / pie
    return new Chart(canvas, {
      type: mode,
      data: {
        labels,
        datasets: [{ data: values,
          backgroundColor: colors.slice(0, values.length),
          borderWidth: 2, borderColor: '#fff', hoverOffset: 8 }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: mode === 'doughnut' ? '62%' : 0,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { padding: 14, font: { size: 11 }, usePointStyle: true, pointStyleWidth: 9 },
          },
          tooltip: { callbacks: { label: (ctx) => ` ${ctx.label}: ${ctx.parsed} de media` } },
        },
      },
    });
  }

  private buildFileName(titulo: string, id: string): string {
    const normalizedTitle = titulo
      .trim().toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return `participantes-${normalizedTitle || id}.pdf`;
  }
}
