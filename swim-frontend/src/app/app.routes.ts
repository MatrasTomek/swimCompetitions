import { Routes } from '@angular/router';
import { adminGuard, userGuard } from './core/guards/auth.guard';

export const routes: Routes = [
  {
    path: '',
    loadComponent: () => import('./public/home/home.component').then(m => m.HomeComponent),
  },
  {
    path: 'zawody/:slug/lista',
    loadComponent: () => import('./public/start-list/start-list.component').then(m => m.StartListComponent),
  },
  {
    path: 'zawody/:slug/wyniki',
    loadComponent: () => import('./public/results/results.component').then(m => m.ResultsComponent),
  },
  {
    path: 'import',
    loadComponent: () => import('./public/import/import.component').then(m => m.ImportComponent),
  },
  {
    path: 'moje/:id/lista',
    loadComponent: () => import('./public/start-list/start-list.component').then(m => m.StartListComponent),
    data: { local: true },
  },
  {
    path: 'rejestracja',
    loadComponent: () => import('./public/register/register.component').then(m => m.RegisterComponent),
  },
  {
    path: 'rodo',
    loadComponent: () => import('./public/rodo/rodo.component').then(m => m.RodoComponent),
  },
  {
    path: 'logowanie',
    loadComponent: () => import('./public/login/login.component').then(m => m.LoginComponent),
  },
  { path: 'admin/login', redirectTo: 'logowanie' },
  {
    path: 'konto/potwierdz',
    loadComponent: () => import('./public/account/verify-email.component').then(m => m.VerifyEmailComponent),
  },
  {
    path: 'konto/zapomniane-haslo',
    loadComponent: () => import('./public/account/forgot-password.component').then(m => m.ForgotPasswordComponent),
  },
  {
    path: 'konto/reset-hasla',
    loadComponent: () => import('./public/account/reset-password.component').then(m => m.ResetPasswordComponent),
  },
  {
    path: 'konto',
    canActivate: [userGuard],
    children: [
      {
        path: '',
        loadComponent: () => import('./account/account.component').then(m => m.AccountComponent),
      },
      {
        path: 'zawodnicy',
        loadComponent: () => import('./account/members.component').then(m => m.MembersComponent),
      },
    ],
  },
  {
    path: 'admin',
    canActivate: [adminGuard],
    children: [
      { path: '', redirectTo: 'zawody', pathMatch: 'full' },
      {
        path: 'zawody',
        loadComponent: () => import('./admin/competitions/competitions.component').then(m => m.CompetitionsComponent),
      },
      {
        path: 'zawody/dodaj',
        loadComponent: () => import('./admin/competitions/competition-form.component').then(m => m.CompetitionFormComponent),
      },
      {
        path: 'zawody/:slug/edytuj',
        loadComponent: () => import('./admin/competitions/competition-form.component').then(m => m.CompetitionFormComponent),
      },
      {
        path: 'zawodnicy',
        loadComponent: () => import('./admin/athletes/athletes.component').then(m => m.AthletesComponent),
      },
      {
        path: 'live',
        loadComponent: () => import('./admin/live/live.component').then(m => m.LiveComponent),
      },
      {
        path: 'uzytkownicy',
        loadComponent: () => import('./admin/users/users.component').then(m => m.UsersComponent),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
