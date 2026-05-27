import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

/**
 * Validator que acepta TANTO email como DNI/CIF
 * Email: usuario@example.com
 * DNI: 12345678X, 12345678-X, 1234 5678 X, etc (8 dígitos + 1 letra, con espacios/guiones opcionales)
 * CIF: A12345678, A-12345678, A 12345678, etc (1 letra + 8 dígitos, con espacios/guiones opcionales)
 */
export function emailOrDocumentValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const value = control.value?.trim() || '';

    if (!value) {
      return { required: true };
    }

    // Validar email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (emailRegex.test(value)) {
      return null; // Email válido
    }

    // Limpiar espacios y guiones para validar documentos
    const cleaned = value.replace(/[\s\-]/g, '').toUpperCase();

    // Validar DNI (8 dígitos + 1 letra)
    // Ejemplos válidos: 12345678X, 12345678-X, 1234 5678 X
    const dniRegex = /^\d{8}[A-Z]$/;
    if (dniRegex.test(cleaned)) {
      return null; // DNI válido
    }

    // Validar CIF (1 letra + 8 dígitos)
    // Ejemplos válidos: A12345678, A-12345678, A 12345678
    const cifRegex = /^[A-Z]\d{8}$/;
    if (cifRegex.test(cleaned)) {
      return null; // CIF válido
    }

    return { invalidIdentifier: true };
  };
}

