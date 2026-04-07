import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map } from 'rxjs/operators';
import { of } from 'rxjs';
import { AuthService } from '../core/services/auth.service';

export const adminGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (!auth.isLoggedIn()) {
    auth.clearSession();
    router.navigate(['/login']);
    return false;
  }

  if (auth.currentUser?.is_admin) return true;

  return auth.me().pipe(
    map((user: any) => user?.is_admin ? true : router.createUrlTree(['/home'])),
    catchError(() => of(router.createUrlTree(['/login'])))
  );
};
