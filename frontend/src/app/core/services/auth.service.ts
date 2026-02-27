import { Injectable } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap, catchError, timeout } from 'rxjs/operators';
import { throwError, BehaviorSubject } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private apiUrl = 'http://localhost:8000/api';

  // Usuario cacheado — evita llamadas extra a /api/me
  private _user = new BehaviorSubject<any>(null);
  currentUser$ = this._user.asObservable();

  get currentUser() { return this._user.value; }

  constructor(private http: HttpClient, private router: Router) {}

  register(data: any) {
    return this.http.post(`${this.apiUrl}/register`, data).pipe(
      tap((res: any) => {
        this.saveToken(res.token);
        this._user.next(res.user);
      }),
      catchError(this.handleError)
    );
  }

  login(data: any) {
    return this.http.post(`${this.apiUrl}/login`, data).pipe(
      tap((res: any) => {
        this.saveToken(res.token);
        this._user.next(res.user);
      }),
      catchError(this.handleError)
    );
  }

  me() {
    // Si ya tenemos el usuario en caché, no hace falta pedir al servidor
    if (this._user.value) {
      return new BehaviorSubject(this._user.value).asObservable();
    }
    return this.http.get(`${this.apiUrl}/me`).pipe(
      timeout(8000),
      tap((u: any) => this._user.next(u)),
      catchError(this.handleError)
    );
  }

  updateProfile(data: any) {
    return this.http.put(`${this.apiUrl}/user/update`, data).pipe(
      tap((res: any) => {
        if (res.user) this._user.next(res.user);
      }),
      catchError(this.handleError)
    );
  }

  deleteAccount() {
    return this.http.delete(`${this.apiUrl}/user`).pipe(
      tap(() => this.logout()),
      catchError(this.handleError)
    );
  }

  logout() {
    // JWT es stateless: basta limpiar el token local.
    // Enviamos logout al servidor en background sin bloquear.
    const token = this.getToken();
    if (token) {
      this.http.post(`${this.apiUrl}/logout`, {}).subscribe();
    }
    this._user.next(null);
    localStorage.removeItem('token');
    this.router.navigate(['/login']);
  }

  saveToken(token: string) {
    localStorage.setItem('token', token);
  }

  getToken(): string | null {
    return localStorage.getItem('token');
  }

  isLoggedIn(): boolean {
    return !!this.getToken();
  }

  private handleError(error: HttpErrorResponse) {
    return throwError(() => error);
  }
}