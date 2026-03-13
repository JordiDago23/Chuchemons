import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';

export const routes: Routes = [
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  {
    path: 'register',
    loadComponent: () => import('./pages/register/register.component').then(m => m.RegisterComponent)
  },
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login.component').then(m => m.LoginComponent)
  },
  {
    path: 'chuchedex',
    loadComponent: () => import('./pages/Chuchedex/chuchedex.component').then(m => m.ChuchedexComponent),
    canActivate: [authGuard]
  },
  {
    path: 'team-selector',
    loadComponent: () => import('./pages/team-selector/team-selector.component').then(m => m.TeamSelectorComponent),
    canActivate: [authGuard]
  },
  {
    path: 'home',
    loadComponent: () => import('./pages/home/home.component').then(m => m.HomeComponent),
    canActivate: [authGuard]
  },
  {
    path: 'profile',
    loadComponent: () => import('./pages/profile/profile.component').then(m => m.ProfileComponent),
    canActivate: [authGuard]
  },
  {
    path: 'mochila',
    loadComponent: () => import('./pages/mochila/mochila.component').then(m => m.MochilaComponent),
    canActivate: [authGuard]
  },
  {
    path: 'admin',
    loadComponent: () => import('./pages/admin/admin.component').then(m => m.AdminComponent),
    canActivate: [authGuard]
  },
  { path: '**', redirectTo: 'login' }
];