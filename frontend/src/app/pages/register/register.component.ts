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

  constructor(private auth: AuthService, private router: Router) {}

  onSubmit() {
    this.error = '';
    this.loading = true;

    this.auth.register(this.form).subscribe({
      next: () => this.router.navigate(['/home']),
      error: (err: any) => {
        this.loading = false;
        this.error = err.error?.message || 'Error al registrarse';
      }
    });
  }
}