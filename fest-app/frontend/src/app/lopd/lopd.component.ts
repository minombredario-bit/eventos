import { Component, Input, OnInit } from '@angular/core';
import { LopdService } from './lopd.service';

@Component({
  selector: 'app-lopd',
  templateUrl: './lopd.component.html',
  styleUrls: ['./lopd.component.scss']
})
export class LopdComponent implements OnInit {
  @Input() userId?: string;
  @Input() entidadId?: string;

  acepto = false;
  loading = true;
  textoLopd: string | null = null;

  constructor(private lopd: LopdService) {}

  ngOnInit(): void {
    if (!this.userId) {
      console.warn('LopdComponent: userId not provided');
      this.loading = false;
      return;
    }

    this.lopd.getUsuario(this.userId).subscribe((u: any) => {
      this.acepto = !!u.aceptoLopd;
      this.loading = false;
      // if entidadId not provided try from user
      if (!this.entidadId && u.entidad && u.entidad['@id']) {
        // extract id from iri
        const iri = u.entidad['@id'];
        const parts = iri.split('/');
        this.entidadId = parts[parts.length - 1];
      }

      if (this.entidadId) {
        this.lopd.getEntidad(this.entidadId).subscribe((e: any) => {
          this.textoLopd = e.textoLopd || null;
        }, () => this.textoLopd = null);
      }
    }, () => {
      this.loading = false;
    });
  }

  aceptar() {
    if (!this.userId) { return; }
    this.lopd.patchAcepto(this.userId, true).subscribe(() => {
      this.acepto = true;
      // optionally emit event or reload app state
      window.location.reload();
    });
  }

  declinar() {
    if (!this.userId) { return; }
    this.lopd.patchAcepto(this.userId, false).subscribe(() => {
      this.acepto = false;
      // log out or redirect
      alert('Has declinado las condiciones. No podrás usar la aplicación.');
      // implement a proper logout flow in your app
    });
  }
}

