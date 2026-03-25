import { Injectable } from '@angular/core';
import {
  EventoResumenApi,
  MenuEventoApi,
  NoFalleroApi,
  PersonaFamiliarApi,
} from './eventos-api';
import { EventSummary, FamilyMember, MenuOption, ParticipantOrigin, PaymentBadgeStatus } from '../models/ui';

interface NoFalleroDeleteMapped {
  id: string;
  eventId: string;
}

@Injectable({ providedIn: 'root' })
export class EventosMapper {
  toEventSummary(evento: EventoResumenApi): EventSummary {
    return {
      id: evento.id,
      title: evento.titulo,
      date: evento.fechaEvento,
      time: evento.horaInicio ?? 'Sin hora',
      location: evento.lugar ?? 'Lugar por confirmar',
      status: this.toUiStatus(evento),
      description: evento.descripcion ?? 'Sin descripción disponible.',
    };
  }

  toFamilyMember(persona: PersonaFamiliarApi | NoFalleroApi): FamilyMember {
    const nombre = this.resolveName(persona);
    const enrollment = persona.inscripcion;

    return {
      id: String(persona.id),
      name: nombre,
      role: persona.parentesco ?? 'Participante',
      origin: this.resolveOrigin(persona),
      personType: persona.tipoPersona === 'infantil' ? 'infantil' : 'adulto',
      avatarInitial: nombre.charAt(0).toUpperCase() || '?',
      notes: persona.observaciones ?? undefined,
      enrollment: enrollment
        ? {
          eventId: enrollment.evento.id,
          eventTitle: enrollment.evento.titulo,
          eventLabel: this.formatEnrollmentEvent(
            enrollment.evento.fechaEvento,
            enrollment.evento.horaInicio,
            enrollment.evento.titulo,
          ),
          paymentStatus: this.toPaymentBadgeStatus(enrollment.estadoPago),
          paymentStatusRaw: enrollment.estadoPago,
        }
        : undefined,
    };
  }

  mapNoFallerosList(payload: unknown, fallbackEventId: string): NoFalleroApi[] {
    if (!Array.isArray(payload)) {
      return [];
    }

    return payload.map((item) => this.mapNoFallero(item, fallbackEventId)).filter((item) => item.id.length > 0);
  }

  mapNoFalleroCreate(payload: unknown, fallbackEventId: string, fallbackName?: string, fallbackLastName?: string): NoFalleroApi {
    const mapped = this.mapNoFallero(payload, fallbackEventId);
    if (mapped.nombre.length === 0) {
      mapped.nombre = fallbackName?.trim() || 'Participante';
    }
    if ((mapped.apellidos ?? '').trim().length === 0 && fallbackLastName?.trim()) {
      mapped.apellidos = fallbackLastName.trim();
    }

    mapped.nombreCompleto = this.resolveName(mapped);
    return mapped;
  }

  mapNoFalleroDelete(payload: unknown, fallbackEventId: string, fallbackNoFalleroId: string): NoFalleroDeleteMapped {
    const source = this.asRecord(payload);
    const eventFromPayload = this.readString(source['eventoId'])
      || this.readString(source['evento_id'])
      || this.readString(this.asRecord(source['evento'])['id'])
      || fallbackEventId;

    return {
      id: this.readString(source['id']) || fallbackNoFalleroId,
      eventId: eventFromPayload,
    };
  }

  toMenuOption(menu: MenuEventoApi): MenuOption {
    return {
      id: menu.id,
      label: menu.nombre,
      description: menu.descripcion ?? '',
      slot: menu.franjaComida,
      compatibility: menu.compatibilidadPersona,
      isPaid: menu.esDePago,
      price: menu.precioBase,
    };
  }

  toPaymentBadgeStatus(estadoPago: string): PaymentBadgeStatus {
    if (estadoPago === 'pagado') {
      return 'pagado';
    }

    if (estadoPago === 'parcial') {
      return 'parcial';
    }

    if (estadoPago === 'no_requiere_pago') {
      return 'no_requiere';
    }

    return 'pendiente';
  }

  private toUiStatus(evento: EventoResumenApi): EventSummary['status'] {
    const inscripcionAbierta = evento.inscripcionAbierta;

    if (inscripcionAbierta === true) {
      return 'abierto';
    }

    if (inscripcionAbierta === false) {
      return 'cerrado';
    }

    if (evento.estado === 'publicado') {
      return 'ultimas_plazas';
    }

    return 'cerrado';
  }

  private resolveName(persona: PersonaFamiliarApi | NoFalleroApi): string {
    if (persona.nombreCompleto && persona.nombreCompleto.trim().length > 0) {
      return persona.nombreCompleto.trim();
    }

    const fullName = [persona.nombre, persona.apellidos].filter(Boolean).join(' ').trim();
    return fullName || 'Participante';
  }

  private resolveOrigin(persona: PersonaFamiliarApi | NoFalleroApi): ParticipantOrigin {
    if ('origen' in persona && persona.origen === 'no_fallero') {
      return 'no_fallero';
    }

    if ('esNoFallero' in persona && persona.esNoFallero === true) {
      return 'no_fallero';
    }

    return 'familiar';
  }

  private mapNoFallero(payload: unknown, fallbackEventId: string): NoFalleroApi {
    const source = this.asRecord(payload);
    const enrollment = this.mapEnrollment(source, fallbackEventId);

    const nombre = this.readString(source['nombre']);
    const apellidos = this.readString(source['apellidos']);
    const nombreCompleto = this.readString(source['nombreCompleto'])
      || this.readString(source['nombre_completo'])
      || [nombre, apellidos].filter(Boolean).join(' ').trim();

    return {
      id: this.readString(source['id']),
      nombre,
      apellidos,
      nombreCompleto,
      parentesco: this.readString(source['parentesco']) || 'Participante',
      tipoPersona: this.readTipoPersona(source['tipoPersona'] ?? source['tipo_persona']),
      observaciones: this.readNullableString(source['observaciones']) ?? null,
      origen: this.readOrigin(source),
      esNoFallero: this.readBoolean(source['esNoFallero'] ?? source['es_no_fallero']) ?? true,
      inscripcion: enrollment,
    };
  }

  private mapEnrollment(source: Record<string, unknown>, fallbackEventId: string): NoFalleroApi['inscripcion'] {
    const inscripcionRaw = source['inscripcion'];
    const inscripcion = this.asRecord(inscripcionRaw);
    const evento = this.asRecord(inscripcion['evento']);
    const fallbackEvento = this.asRecord(source['evento']);

    const eventId = this.readString(evento['id'])
      || this.readString(inscripcion['eventoId'])
      || this.readString(source['eventoId'])
      || this.readString(source['evento_id'])
      || this.readString(fallbackEvento['id'])
      || fallbackEventId;

    const eventTitle = this.readString(evento['titulo'])
      || this.readString(inscripcion['eventoTitulo'])
      || this.readString(inscripcion['evento_titulo'])
      || this.readString(source['eventoTitulo'])
      || this.readString(source['evento_titulo'])
      || this.readString(fallbackEvento['titulo'])
      || 'Evento';

    const eventDate = this.readString(evento['fechaEvento'])
      || this.readString(inscripcion['fechaEvento'])
      || this.readString(inscripcion['fecha_evento'])
      || this.readString(source['fechaEvento'])
      || this.readString(source['fecha_evento'])
      || this.readString(fallbackEvento['fechaEvento'])
      || this.readString(fallbackEvento['fecha_evento'])
      || '';

    const eventTime = this.readNullableString(evento['horaInicio'])
      ?? this.readNullableString(inscripcion['horaInicio'])
      ?? this.readNullableString(inscripcion['hora_inicio'])
      ?? this.readNullableString(source['horaInicio'])
      ?? this.readNullableString(source['hora_inicio'])
      ?? this.readNullableString(fallbackEvento['horaInicio'])
      ?? this.readNullableString(fallbackEvento['hora_inicio'])
      ?? null;

    const estadoPago = this.normalizePaymentRaw(
      this.readString(inscripcion['estadoPago'])
      || this.readString(inscripcion['estado_pago'])
      || this.readString(source['estadoPago'])
      || this.readString(source['estado_pago'])
      || this.readString(source['paymentStatus'])
      || this.readString(source['payment_status']),
    );

    if (eventId.length === 0 && eventTitle === 'Evento' && eventDate.length === 0 && estadoPago === 'pendiente' && !inscripcionRaw) {
      return null;
    }

    return {
      evento: {
        id: eventId,
        titulo: eventTitle,
        fechaEvento: eventDate,
        horaInicio: eventTime,
      },
      estadoPago,
    };
  }

  private normalizePaymentRaw(value: string): string {
    if (value === 'pagado' || value === 'parcial' || value === 'pendiente' || value === 'no_requiere_pago') {
      return value;
    }

    if (value === 'paid') {
      return 'pagado';
    }

    if (value === 'partial') {
      return 'parcial';
    }

    if (value === 'not_required') {
      return 'no_requiere_pago';
    }

    return 'pendiente';
  }

  private readOrigin(source: Record<string, unknown>): NoFalleroApi['origen'] {
    const origin = this.readString(source['origen']) || this.readString(source['origin']);
    return origin === 'familiar' ? 'familiar' : 'no_fallero';
  }

  private readTipoPersona(value: unknown): NoFalleroApi['tipoPersona'] {
    return value === 'infantil' ? 'infantil' : 'adulto';
  }

  private readString(value: unknown): string {
    return typeof value === 'string' ? value.trim() : '';
  }

  private readNullableString(value: unknown): string | null {
    if (value === null || value === undefined) {
      return null;
    }

    if (typeof value !== 'string') {
      return null;
    }

    const normalized = value.trim();
    return normalized.length > 0 ? normalized : null;
  }

  private readBoolean(value: unknown): boolean | null {
    return typeof value === 'boolean' ? value : null;
  }

  private asRecord(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' ? (value as Record<string, unknown>) : {};
  }

  private formatEnrollmentEvent(fechaEvento: string, horaInicio: string | null | undefined, titulo: string): string {
    const normalizedDate = fechaEvento.includes('T') ? fechaEvento.slice(0, 10) : fechaEvento;
    const [yearRaw, monthRaw, dayRaw] = normalizedDate.split('-');

    const year = Number(yearRaw);
    const month = Number(monthRaw);
    const day = Number(dayRaw);

    if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
      return titulo;
    }

    const eventDate = new Date(year, month - 1, day);
    if (Number.isNaN(eventDate.getTime())) {
      return titulo;
    }

    const dateLabel = eventDate.toLocaleDateString('es-ES', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });

    const timeLabel = horaInicio?.match(/^\d{2}:\d{2}/)?.[0] ?? null;

    return timeLabel
      ? `${titulo} · ${dateLabel} ${timeLabel}`
      : `${titulo} · ${dateLabel}`;
  }
}
