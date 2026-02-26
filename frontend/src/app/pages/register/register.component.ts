import { Component, OnInit } from '@angular/core';
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
export class RegisterComponent implements OnInit {
  form = { nombre: '', apellidos: '', email: '', password: '', password_confirmation: '' };
  error = '';
  loading = false;
  passwordMismatch = false;

  constructor(private auth: AuthService, private router: Router) {}

  ngOnInit(): void {
    // Inicialización si es necesaria en el futuro
  }

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
        console.error('Error de registro:', err);
        if (err.error?.errors) {
          this.error = Object.values(err.error.errors).flat().join(', ') as string;
        } else if (err.error?.message) {
          this.error = err.error.message;
        } else {
          this.error = 'Error en el registro. Por favor, intenta de nuevo.';
        }
      }
    });
  }
}