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
  form = { nom: '', cognoms: '', email: '', password: '', password_confirmation: '', player_id: '' };
  error = '';
  loading = false;
  passwordMismatch = false;

  constructor(private auth: AuthService, private router: Router) {}

  ngOnInit(): void {
    this.form.player_id = this.generatePlayerId();
  }

  private generatePlayerId(): string {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const randomPart = Array.from({ length: 6 }, () =>
      chars.charAt(Math.floor(Math.random() * chars.length))
    ).join('');
    return `CHU-${randomPart}`;
  }

  onSubmit() {
    this.error = '';
    this.passwordMismatch = false;

    if (this.form.password !== this.form.password_confirmation) {
      this.passwordMismatch = true;
      return;
    }

    this.loading = true;

    this.auth.register(this.form).subscribe({
      next: () => this.router.navigate(['/home']),
      error: (err: any) => {
        this.loading = false;
        this.error = err.error?.message || 'Error en registrar-se';
      }
    });
  }
}