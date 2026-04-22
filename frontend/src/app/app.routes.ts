import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';
import { adminGuard } from './guards/admin.guard';

export const routes: Routes = [
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  // Aliases requerits pel nivell 2
  { path: 'xuxedex',   redirectTo: 'chuchedex', pathMatch: 'full' },
  { path: 'inventory', redirectTo: 'mochila',   pathMatch: 'full' },
  { path: 'friends',   redirectTo: 'amigos',    pathMatch: 'full' },
  { path: 'battles',   redirectTo: 'batalla',   pathMatch: 'full' },
  {
    path: 'register',
    title: 'Registro | Chuchemons',
    data: {
      description: 'Crea tu cuenta en Chuchemons para capturar, coleccionar y gestionar tus Chuchemons.',
      keywords: 'registro, chuchemons, cuenta, xuxemons'
    },
    loadComponent: () => import('./pages/register/register.component').then(m => m.RegisterComponent)
  },
  {
    path: 'login',
    title: 'Login | Chuchemons',
    data: {
      description: 'Inicia sesion en Chuchemons con tu ID de jugador y password.',
      keywords: 'login, acceso, chuchemons, jugador'
    },
    loadComponent: () => import('./pages/login/login.component').then(m => m.LoginComponent)
  },
  {
    path: 'chuchedex',
    title: 'Chuchedex | Chuchemons',
    data: {
      description: 'Explora todos los Chuchemons, filtra por tipo y revisa tu coleccion.',
      keywords: 'chuchedex, coleccion, agua, tierra, aire'
    },
    loadComponent: () => import('./pages/Chuchedex/chuchedex.component').then(m => m.ChuchedexComponent),
    canActivate: [authGuard]
  },
  {
    path: 'team-selector',
    title: 'Equipo | Chuchemons',
    loadComponent: () => import('./pages/team-selector/team-selector.component').then(m => m.TeamSelectorComponent),
    canActivate: [authGuard]
  },
  {
    path: 'home',
    title: 'Inicio | Chuchemons',
    data: {
      description: 'Panel principal de Chuchemons con recompensas, salud, equipo y progreso.',
      keywords: 'inicio, dashboard, equipo, recompensas'
    },
    loadComponent: () => import('./pages/home/home.component').then(m => m.HomeComponent),
    canActivate: [authGuard]
  },
  {
    path: 'profile',
    title: 'Perfil | Chuchemons',
    data: {
      description: 'Edita tu perfil de entrenador, credenciales y datos personales en Chuchemons.',
      keywords: 'perfil, usuario, cuenta, entrenador'
    },
    loadComponent: () => import('./pages/profile/profile.component').then(m => m.ProfileComponent),
    canActivate: [authGuard]
  },
  {
    path: 'mochila',
    title: 'Mochila | Chuchemons',
    data: {
      description: 'Gestiona tu inventario de Xuxes, vacunas y objetos en la mochila.',
      keywords: 'inventario, mochila, xuxes, vacunas'
    },
    loadComponent: () => import('./pages/mochila/mochila.component').then(m => m.MochilaComponent),
    canActivate: [authGuard]
  },
  {
    path: 'amics',
    redirectTo: 'amigos',
    pathMatch: 'full'
  },
  {
    path: 'amigos',
    title: 'Amigos | Chuchemons',
    data: {
      description: 'Busca jugadores, envia solicitudes y administra tu lista de amigos.',
      keywords: 'amigos, social, solicitudes, friends'
    },
    loadComponent: () => import('./pages/amigos/amigos.component').then(m => m.AmigosComponent),
    canActivate: [authGuard]
  },
  {
    path: 'batalla',
    title: 'Batalla | Chuchemons',
    data: {
      description: 'Arena de batalla entre amigos: desafios, seleccion de Xuxemons y resolucion de duelos.',
      keywords: 'batalla, arena, pvp, amigos, duelo'
    },
    loadComponent: () => import('./pages/batalla/batalla.component').then(m => m.BatallaComponent),
    canActivate: [authGuard]
  },
  {
    path: 'batalla/:battleId',
    title: 'Combate | Chuchemons',
    data: {
      description: 'Combate en curso entre dos entrenadores en la Arena de Batalla.',
      keywords: 'combate, batalla, duelo, xuxemon'
    },
    loadComponent: () => import('./pages/batalla/batalla.component').then(m => m.BatallaComponent),
    canActivate: [authGuard]
  },
  {
    path: 'admin',
    title: 'Admin | Chuchemons',
    data: {
      description: 'Panel de administracion para configurar parametros del juego y gestionar jugadores.',
      keywords: 'admin, configuracion, usuarios, juego'
    },
    loadComponent: () => import('./pages/admin/admin.component').then(m => m.AdminComponent),
    canActivate: [authGuard, adminGuard]
  },
  { path: '**', redirectTo: 'login' }
];