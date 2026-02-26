import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css']
})
export class LoginComponent {
  form = { player_id: '', password: '' };
  error = '';
  loading = false;

  constructor(private auth: AuthService, private router: Router) {}

  onSubmit() {
    this.error = '';
    
    if (!this.form.player_id || !this.form.password) {
      this.error = 'Por favor completa todos los campos';
      return;
    }

    this.loading = true;
    console.log('Intentando login con:', this.form.player_id);

    this.auth.login(this.form).subscribe({
      next: (response: any) => {
        console.log('Login exitoso:', response);
        console.log('Token guardado:', this.auth.getToken());
        console.log('isLoggedIn:', this.auth.isLoggedIn());
        this.loading = false;
        this.error = '';
        console.log('Navegando a /home...');
        setTimeout(() => {
          this.router.navigate(['/home']).then(success => {
            console.log('Navegación exitosa:', success);
          }).catch(err => {
            console.error('Error en navegación:', err);
          });
        }, 500);
      },
      error: (err: any) => {
        this.loading = false;
        console.error('Error de login completo:', err);
        console.error('Status HTTP:', err.status);
        if (err.error?.message) {
          this.error = err.error.message;
        } else if (err.message) {
          this.error = err.message;
        } else {
          this.error = 'Credenciales incorrectas o error de conexión';
        }
        console.error('Mensaje de error mostrado:', this.error);
      }
    });
  }
}