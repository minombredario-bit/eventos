import { inject, Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';

export interface PasskeyCredentialInfo {
  id: string;
  deviceName: string;
  createdAt: string;
  lastUsedAt: string | null;
}

/**
 * BiometricService — WebAuthn (FIDO2 passkeys) integration.
 *
 * Wraps the browser's Web Authentication API to provide:
 *  - Device passkey registration (after first password login)
 *  - Passkey-based login (biometric unlock on mobile PWA)
 *  - Credential management (list / delete)
 */
@Injectable({ providedIn: 'root' })
export class BiometricService {
  private readonly http = inject(HttpClient);
  private readonly base = environment.apiUrl;

  readonly isSupported = signal(this.checkSupport());
  readonly isPlatformAvailable = signal(this.checkSupport()); // Show button if WebAuthn is supported

  constructor() {
    this.detectPlatformAuthenticator();
  }

  // ── Support detection ───────────────────────────────────────────────────

  private checkSupport(): boolean {
    return typeof window !== 'undefined'
      && typeof window.PublicKeyCredential !== 'undefined';
  }

  private detectPlatformAuthenticator(): void {
    if (!this.isSupported()) return;

    // Try to detect platform authenticator, but show button anyway if WebAuthn is supported
    // Some mobile browsers report false even when biometric is available
    PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
      .then((available) => {
        // Keep button visible if WebAuthn is supported, regardless of this detection
        if (available) {
          this.isPlatformAvailable.set(true);
        }
      })
      .catch(() => {
        // Ignore error, keep button visible
      });
  }

  // ── Registration ────────────────────────────────────────────────────────

  /**
   * Full registration flow:
   *  1. Fetch options from backend
   *  2. Call navigator.credentials.create() (OS triggers biometric/PIN)
   *  3. Send attestation to backend for storage
   */
  async registerPasskey(deviceName?: string): Promise<PasskeyCredentialInfo> {
    if (!this.isSupported()) {
      throw new Error('WebAuthn no está disponible en este navegador.');
    }

    // 1. Get registration options from backend
    const options = await firstValueFrom(
      this.http.get<any>(`${this.base}/auth/passkey/register/options`),
    );

    // 2. Create credential (triggers OS biometric dialog)
    const credential = await navigator.credentials.create({
      publicKey: this.prepareRegistrationOptions(options),
    }) as PublicKeyCredential;

    if (!credential) {
      throw new Error('El registro biométrico fue cancelado.');
    }

    const response = credential.response as AuthenticatorAttestationResponse;

    // 3. Send to backend
    const result = await firstValueFrom(
      this.http.post<PasskeyCredentialInfo>(`${this.base}/auth/passkey/register`, {
        id: credential.id,
        rawId: this.bufferToBase64Url(credential.rawId),
        type: credential.type,
        deviceName: deviceName ?? this.getDeviceName(),
        response: {
          attestationObject: this.bufferToBase64Url(response.attestationObject),
          clientDataJSON: this.bufferToBase64Url(response.clientDataJSON),
        },
      }),
    );

    return result;
  }

  // ── Authentication ──────────────────────────────────────────────────────

  /**
   * Full authentication flow:
   *  1. Fetch challenge options from backend (optionally filtered by email)
   *  2. Call navigator.credentials.get() (OS triggers biometric/PIN)
   *  3. Send assertion to backend, receive JWT
   */
  async loginWithPasskey(email?: string): Promise<string> {
    if (!this.isSupported()) {
      throw new Error('WebAuthn no está disponible en este navegador.');
    }

    // 1. Get authentication options
    const options = await firstValueFrom(
      this.http.post<any>(`${this.base}/auth/passkey/login/options`, { email }),
    );

    const cacheKey: string = options._cacheKey;

    // 2. Get credential (triggers OS biometric dialog)
    const assertion = await navigator.credentials.get({
      publicKey: this.prepareAuthenticationOptions(options),
    }) as PublicKeyCredential;

    if (!assertion) {
      throw new Error('La autenticación biométrica fue cancelada.');
    }

    const response = assertion.response as AuthenticatorAssertionResponse;

    // 3. Verify with backend
    const result = await firstValueFrom(
      this.http.post<{ token: string }>(`${this.base}/auth/passkey/login`, {
        id: assertion.id,
        rawId: this.bufferToBase64Url(assertion.rawId),
        type: assertion.type,
        _cacheKey: cacheKey,
        response: {
          authenticatorData: this.bufferToBase64Url(response.authenticatorData),
          clientDataJSON: this.bufferToBase64Url(response.clientDataJSON),
          signature: this.bufferToBase64Url(response.signature),
          userHandle: response.userHandle
            ? this.bufferToBase64Url(response.userHandle)
            : null,
        },
      }),
    );

    return result.token;
  }

  // ── Credential management ───────────────────────────────────────────────

  listCredentials(): import('rxjs').Observable<PasskeyCredentialInfo[]> {
    return this.http.get<PasskeyCredentialInfo[]>(`${this.base}/auth/passkey/credentials`);
  }

  deleteCredential(id: string): import('rxjs').Observable<{ ok: boolean }> {
    return this.http.delete<{ ok: boolean }>(`${this.base}/auth/passkey/credentials/${id}`);
  }

  // ── Helpers ─────────────────────────────────────────────────────────────

  private prepareRegistrationOptions(opts: any): PublicKeyCredentialCreationOptions {
    return {
      challenge: this.base64UrlToBuffer(opts.challenge),
      rp: opts.rp,
      user: {
        id: this.base64UrlToBuffer(opts.user.id),
        name: opts.user.name,
        displayName: opts.user.displayName,
      },
      pubKeyCredParams: opts.pubKeyCredParams,
      authenticatorSelection: opts.authenticatorSelection,
      timeout: opts.timeout ?? 60000,
      attestation: opts.attestation ?? 'none',
      excludeCredentials: (opts.excludeCredentials ?? []).map((c: any) => ({
        id: this.base64UrlToBuffer(c.id),
        type: c.type,
      })),
    };
  }

  private prepareAuthenticationOptions(opts: any): PublicKeyCredentialRequestOptions {
    return {
      challenge: this.base64UrlToBuffer(opts.challenge),
      rpId: opts.rpId,
      timeout: opts.timeout ?? 60000,
      userVerification: opts.userVerification ?? 'preferred',
      allowCredentials: (opts.allowCredentials ?? []).map((c: any) => ({
        id: this.base64UrlToBuffer(c.id),
        type: c.type,
        transports: c.transports || ['internal', 'usb', 'nfc', 'ble'],
      })),
    };
  }

  private base64UrlToBuffer(base64url: string): ArrayBuffer {
    const padded = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const padding = '='.repeat((4 - (padded.length % 4)) % 4);
    const binary = atob(padded + padding);
    const buffer = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      buffer[i] = binary.charCodeAt(i);
    }
    return buffer.buffer;
  }

  private bufferToBase64Url(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
  }

  private getDeviceName(): string {
    const ua = navigator.userAgent;
    if (/iPhone/.test(ua)) return 'iPhone';
    if (/iPad/.test(ua)) return 'iPad';
    if (/Android/.test(ua)) return 'Android';
    if (/Macintosh/.test(ua)) return 'Mac';
    if (/Windows/.test(ua)) return 'Windows';
    return 'Dispositivo';
  }
}

