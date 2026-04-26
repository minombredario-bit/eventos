import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { NgClass } from '@angular/common';
import { RouterLink } from '@angular/router';
import { EventSummary } from '../../../eventos/domain/eventos.models';
import {formatDay, formatMonth} from '../../../../core/utils/date.utils';

@Component({
  selector: 'app-event-card',
  standalone: true,
  imports: [RouterLink, NgClass],
  templateUrl: './event-card.html',
  styleUrl: './event-card.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class EventCard {
  readonly event = input.required<EventSummary>();

  protected readonly statusLabel = computed(() => {
    const status = this.event().status;
    if (status === 'abierto') return '';
    if (status === 'ultimas_plazas') return 'Últimas plazas';
    return 'Inscripción cerrada';
  });
  protected readonly formatEventDay = formatDay;
  protected readonly formatEventMonth = formatMonth;
}
