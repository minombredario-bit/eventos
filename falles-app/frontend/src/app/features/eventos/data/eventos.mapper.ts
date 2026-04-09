import { Injectable } from '@angular/core';
import {
  EventoResumenApi,
  MenuEventoApi,
  InvitadoApi,
  PersonaFamiliarApi,
  RelacionUsuarioApi,
} from './eventos.api';
import { ActivityOption, EventSummary, FamilyMember, ParticipantOrigin, PaymentBadgeStatus } from '../domain/eventos.models';

interface InvitadoDeleteMapped {
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

  toFamilyMember(persona: PersonaFamiliarApi | InvitadoApi): FamilyMember {
    const origin = this.resolveOrigin(persona);
    const nombre = this.resolveName(persona);
    const enrollment = persona.inscripcion;

    return {
      id: String(persona.id),
      name: nombre,
      role: origin === 'invitado' ? 'Invitado' : (persona.parentesco ?? 'Familiar'),
      origin,
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

  toFamilyMemberFromRelacion(relacion: RelacionUsuarioApi, currentUserId: string): FamilyMember | null {
    const relacionado = relacion.usuarioOrigen.id === currentUserId
      ? relacion.usuarioDestino
      : relacion.usuarioOrigen;

    const id = String(relacionado.id ?? '').trim();
    if (!id) {
      return null;
    }

    const nombreCompleto = String(relacionado.nombreCompleto ?? '').trim();
    const nombre = String(relacionado.nombre ?? '').trim();
    const apellidos = String(relacionado.apellidos ?? '').trim();
    const displayName = nombreCompleto || [nombre, apellidos].filter(Boolean).join(' ').trim() || `Usuario ${id}`;

    return {
      id,
      name: displayName,
      role: relacion.tipoRelacion || 'Familiar',
      origin: 'familiar',
      personType: 'adulto',
      avatarInitial: displayName.charAt(0).toUpperCase() || 'U',
    };
  }

  mapInvitadosList(payload: unknown, fallbackEventId: string): InvitadoApi[] {
    if (!Array.isArray(payload)) {
      return [];
    }

    return payload.map((item) => this.mapInvitado(item, fallbackEventId)).filter((item) => item.id.length > 0);
  }

  mapInvitadoCreate(payload: unknown, fallbackEventId: string, fallbackName?: string, fallbackLastName?: string): InvitadoApi {
    const mapped = this.mapInvitado(payload, fallbackEventId);
    if (mapped.nombre.length === 0) {
      mapped.nombre = fallbackName?.trim() || 'Invitado';
    }
    if ((mapped.apellidos ?? '').trim().length === 0 && fallbackLastName?.trim()) {
      mapped.apellidos = fallbackLastName.trim();
    }

    mapped.nombreCompleto = this.resolveName(mapped);
    return mapped;
  }

  mapInvitadoDelete(payload: unknown, fallbackEventId: string, fallbackInvitadoId: string): InvitadoDeleteMapped {
    const source = this.asRecord(payload);
    const eventFromPayload = this.readString(source['eventoId'])
      || this.readString(source['evento_id'])
      || this.readString(this.asRecord(source['evento'])['id'])
      || fallbackEventId;

    return {
      id: this.readString(source['id']) || fallbackInvitadoId,
      eventId: eventFromPayload,
    };
  }

  toActivityOption(menu: MenuEventoApi): ActivityOption {
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

  // Compatibilidad temporal: nombre antiguo.
  toMenuOption(menu: MenuEventoApi): ActivityOption {
    return this.toActivityOption(menu);
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

  private resolveName(persona: PersonaFamiliarApi | InvitadoApi): string {
    if (persona.nombreCompleto && persona.nombreCompleto.trim().length > 0) {
      return persona.nombreCompleto.trim();
    }

    const fullName = [persona.nombre, persona.apellidos].filter(Boolean).join(' ').trim();
    const origin = this.resolveOrigin(persona);
    return fullName || (origin === 'invitado' ? 'Invitado' : 'Participante');
  }

  private resolveOrigin(persona: PersonaFamiliarApi | InvitadoApi): ParticipantOrigin {
    if ('origen' in persona && persona.origen === 'invitado') {
      return 'invitado';
    }

    if ('esInvitado' in persona && persona.esInvitado === true) {
      return 'invitado';
    }

    return 'familiar';
  }

  private mapInvitado(payload: unknown, fallbackEventId: string): InvitadoApi {
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
      parentesco: this.readString(source['parentesco']) || 'Invitado',
      tipoPersona: this.readTipoPersona(source['tipoPersona'] ?? source['tipo_persona']),
      observaciones: this.readNullableString(source['observaciones']) ?? null,
      origen: this.readOrigin(source),
      esInvitado: this.readBoolean(source['esInvitado'] ?? source['es_invitado']) ?? true,
      inscripcion: enrollment,
    };
  }

  private mapEnrollment(source: Record<string, unknown>, fallbackEventId: string): InvitadoApi['inscripcion'] {
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

  private readOrigin(source: Record<string, unknown>): InvitadoApi['origen'] {
    const origin = this.readString(source['origen']) || this.readString(source['origin']);
    return origin === 'familiar' ? 'familiar' : 'invitado';
  }

  private readTipoPersona(value: unknown): InvitadoApi['tipoPersona'] {
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
