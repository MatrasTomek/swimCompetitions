import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';

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
    path: 'admin/login',
    loadComponent: () => import('./admin/login/login.component').then(m => m.LoginComponent),
  },
  {
    path: 'admin',
    canActivate: [authGuard],
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
        path: 'import',
        loadComponent: () => import('./admin/import/import.component').then(m => m.ImportComponent),
      },
      {
        path: 'zawodnicy',
        loadComponent: () => import('./admin/athletes/athletes.component').then(m => m.AthletesComponent),
      },
      {
        path: 'live',
        loadComponent: () => import('./admin/live/live.component').then(m => m.LiveComponent),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
