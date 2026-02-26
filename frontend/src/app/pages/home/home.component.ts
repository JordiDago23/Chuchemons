import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent implements OnInit {
  user: any = null;
  loading = true;
  error = '';

  constructor(private auth: AuthService) {}

  ngOnInit() {
    console.log('Home: Componente inicializado');
    console.log('Home: Token presente:', !!this.auth.getToken());
    
    this.auth.me().subscribe({
      next: (data) => {
        console.log('Home: Datos del usuario recibidos:', data);
        this.user = data;
        this.loading = false;
      },
      error: (err) => {
        console.error('Home: Error al obtener datos del usuario:', err);
        this.error = 'Error al cargar los datos del usuario';
        this.loading = false;
        this.auth.logout();
      }
    });
  }

  logout() {
    console.log('Home: Cerrando sesión');
    this.auth.logout();
  }
}