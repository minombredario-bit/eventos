import { Injectable } from '@angular/core';
import { CanActivate, Router } from '@angular/router';
import { LopdService } from './lopd.service';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';

@Injectable({ providedIn: 'root' })
export class LopdGuard implements CanActivate {
  constructor(private lopd: LopdService, private router: Router) {}

  canActivate(): Observable<boolean> {
    // You need to supply userId in your app (e.g. from auth token). Here we read from localStorage for example.
    const userId = localStorage.getItem('currentUserId');
    if (!userId) {
      // If no user id, allow navigation (or redirect to login depending on your flow)
      return of(true);
    }

    return this.lopd.getUsuario(userId).pipe(
      map((u: any) => {
        if (u.aceptoLopd) {
          return true;
        }
        // If not accepted redirect to a route that shows the LOPD (create it)
        this.router.navigate(['/lopd'], { queryParams: { userId } });
        return false;
      }),
      catchError(() => {
        // on error allow or redirect to login
        return of(true);
      })
    );
  }
}

