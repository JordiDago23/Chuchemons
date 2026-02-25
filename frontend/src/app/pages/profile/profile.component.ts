import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './profile.component.html',
  styleUrls: ['./profile.component.css']
})
export class ProfileComponent implements OnInit {
  form: any = { nombre: '', apellidos: '', email: '', password: '', password_confirmation: '' };
  error = '';
  success = '';
  loading = false;
  showConfirm = false;

  constructor(private auth: AuthService) {}

  ngOnInit() {
    this.auth.me().subscribe({
      next: (user: any) => {
        this.form.nombre    = user.nombre;
        this.form.apellidos = user.apellidos;
        this.form.email     = user.email;
      }
    });
  }

  onUpdate() {
    this.error = '';
    this.success = '';
    this.loading = true;

    const data: any = {
      nombre:    this.form.nombre,
      apellidos: this.form.apellidos,
      email:     this.form.email,
    };

    if (this.form.password) {
      data.password = this.form.password;
      data.password_confirmation = this.form.password_confirmation;
    }

    this.auth.updateProfile(data).subscribe({
      next: () => {
        this.loading = false;
        this.success = 'Perfil actualizado correctamente';
        this.form.password = '';
        this.form.password_confirmation = '';
      },
      error: (err) => {
        this.loading = false;
        this.error = err.error?.message || 'Error al actualizar el perfil';
      }
    });
  }

  confirmDelete() {
    this.showConfirm = true;
  }

  onDelete() {
    this.auth.deleteAccount().subscribe();
  }
}