import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { cardInAnim, pokeballDropAnim, pokeballSpinAnim, fadeInDownAnim } from '../../animations/shared.animations';

interface ParticleItem { emoji: string; left: string; top: string; duration: string; delay: string; }

const BG_PARTICLES: ParticleItem[] = [
  { emoji: '🍬', left:  '5%', top: '12%', duration: '4.2s', delay: '0s'   },
  { emoji: '🍓', left: '18%', top: '70%', duration: '5.1s', delay: '0.6s' },
  { emoji: '⭐', left: '80%', top:  '8%', duration: '3.8s', delay: '1.1s' },
  { emoji: '🍋', left: '88%', top: '60%', duration: '4.7s', delay: '0.3s' },
  { emoji: '🥤', left: '70%', top: '85%', duration: '5.5s', delay: '1.5s' },
  { emoji: '🍊', left: '35%', top:  '5%', duration: '4.0s', delay: '0.9s' },
  { emoji: '🍬', left: '55%', top: '90%', duration: '3.6s', delay: '2.0s' },
  { emoji: '🍓', left:  '3%', top: '45%', duration: '4.9s', delay: '1.8s' },
];

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css'],
  animations: [cardInAnim, pokeballDropAnim, pokeballSpinAnim, fadeInDownAnim],
})
export class LoginComponent {
  readonly bgParticles = BG_PARTICLES;

  form: FormGroup;
  error = '';
  loading = false;
  showPassword = false;

  constructor(private fb: FormBuilder, private auth: AuthService, private router: Router) {
    this.form = this.fb.group({
      player_id: ['', [Validators.required]],
      password:  ['', [Validators.required, Validators.minLength(6)]]
    });
  }

  get playerId() { return this.form.get('player_id')!; }
  get password() { return this.form.get('password')!; }

  togglePasswordVisibility() {
    this.showPassword = !this.showPassword;
  }

  onSubmit() {
    this.error = '';
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.loading = true;
    this.auth.login(this.form.value).subscribe({
      next: () => {
        this.loading = false;
        this.router.navigate(['/home']);
      },
      error: (err: any) => {
        this.loading = false;
        this.error = err.error?.message || 'Credenciales incorrectas o error de conexión';
      }
    });
  }
}

