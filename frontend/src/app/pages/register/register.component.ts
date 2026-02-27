import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.css']
})
export class RegisterComponent {
  form = { nombre: '', apellidos: '', email: '', password: '', password_confirmation: '' };
  error = '';
  loading = false;
  passwordMismatch = false;

  constructor(private auth: AuthService, private router: Router) {}

  onSubmit() {
    this.error = '';
    this.passwordMismatch = false;

    if (this.form.password !== this.form.password_confirmation) {
      this.passwordMismatch = true;
      return;
    }

    if (!this.form.nombre || !this.form.apellidos || !this.form.email || !this.form.password) {
      this.error = 'Todos los campos son requeridos';
      return;
    }

    this.loading = true;

    this.auth.register(this.form).subscribe({
      next: () => {
        this.loading = false;
        this.router.navigate(['/home']);
      },
      error: (err: any) => {
        this.loading = false;
        if (err.error?.errors) {
          this.error = Object.values(err.error.errors).flat().join(', ') as string;
        } else {
          this.error = err.error?.message || 'Error en el registro. Por favor, intenta de nuevo.';
        }
      }
    });
  }
}
