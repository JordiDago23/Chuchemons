import { Injectable } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap, catchError, timeout } from 'rxjs/operators';
import { throwError } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient, private router: Router) {}

  register(data: any) {
    console.log('AuthService: Enviando registro...');
    return this.http.post(`${this.apiUrl}/register`, data).pipe(
      tap((res: any) => {
        console.log('AuthService: Respuesta de registro recibida:', res);
        this.saveToken(res.token);
      }),
      catchError(this.handleError)
    );
  }

  login(data: any) {
    console.log('AuthService: Enviando login...');
    return this.http.post(`${this.apiUrl}/login`, data).pipe(
      tap((res: any) => {
        console.log('AuthService: Respuesta de login recibida:', res);
        this.saveToken(res.token);
      }),
      catchError(this.handleError)
    );
  }

  me() {
    return this.http.get(`${this.apiUrl}/me`).pipe(
      timeout(8000),
      catchError(this.handleError)
    );
  }

  updateProfile(data: any) {
    return this.http.put(`${this.apiUrl}/user/update`, data).pipe(
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
    this.http.post(`${this.apiUrl}/logout`, {}).subscribe({
      error: () => {
        // Error al hacer logout en el servidor, pero continuar locally
        localStorage.removeItem('token');
        this.router.navigate(['/login']);
      }
    });
    localStorage.removeItem('token');
    this.router.navigate(['/login']);
  }

  saveToken(token: string) {
    console.log('AuthService: Guardando token en localStorage...');
    console.log('Token:', token.substring(0, 20) + '...');
    localStorage.setItem('token', token);
    console.log('AuthService: Token guardado. Verificando...');
    console.log('Token en localStorage:', !!localStorage.getItem('token'));
  }

  getToken(): string | null {
    const token = localStorage.getItem('token');
    console.log('AuthService: getToken() -', token ? 'Token presente' : 'Sin token');
    return token;
  }

  isLoggedIn(): boolean {
    const result = !!this.getToken();
    console.log('AuthService: isLoggedIn() -', result);
    return result;
  }

  private handleError(error: HttpErrorResponse) {
    console.error('AuthService: HTTP Error');
    console.error('Status:', error.status);
    console.error('StatusText:', error.statusText);
    console.error('Error body:', error.error);
    return throwError(() => error);
  }
}