import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../core/services/auth.service';

export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  const isLoggedIn = auth.isLoggedIn();
  console.log('Guard: Verificando login. Token presente:', !!auth.getToken());
  console.log('Guard: isLoggedIn():', isLoggedIn);

  if (isLoggedIn) {
    console.log('Guard: Acceso permitido');
    return true;
  }

  console.log('Guard: Acceso denegado, redirigiendo a login');
  router.navigate(['/login']);
  return false;
};