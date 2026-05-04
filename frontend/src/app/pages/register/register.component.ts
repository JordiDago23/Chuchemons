import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  ReactiveFormsModule, FormBuilder, FormGroup,
  Validators, AbstractControl, ValidationErrors
} from '@angular/forms';
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

function passwordMatchValidator(g: AbstractControl): ValidationErrors | null {
  const pw      = g.get('password')?.value;
  const confirm = g.get('password_confirmation')?.value;
  return pw === confirm ? null : { passwordMatch: true };
}

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.css'],
  animations: [cardInAnim, pokeballDropAnim, pokeballSpinAnim, fadeInDownAnim],
})
export class RegisterComponent {
  readonly bgParticles = BG_PARTICLES;

  form: FormGroup;
  error = '';
  loading = false;
  registeredId = '';
  showPassword = false;
  showPasswordConfirmation = false;

  constructor(private fb: FormBuilder, private auth: AuthService, public router: Router) {
    this.form = this.fb.group({
      nombre:                ['', [Validators.required]],
      apellidos:             ['', [Validators.required]],
      email:                 ['', [Validators.required, Validators.email]],
      password:              ['', [Validators.required, Validators.minLength(6)]],
      password_confirmation: ['', [Validators.required]]
    }, { validators: passwordMatchValidator });
  }

  get nombre()               { return this.form.get('nombre')!; }
  get apellidos()            { return this.form.get('apellidos')!; }
  get email()                { return this.form.get('email')!; }
  get password()             { return this.form.get('password')!; }
  get passwordConfirmation() { return this.form.get('password_confirmation')!; }

  togglePasswordVisibility() {
    this.showPassword = !this.showPassword;
  }

  togglePasswordConfirmationVisibility() {
    this.showPasswordConfirmation = !this.showPasswordConfirmation;
  }

  get previewPlayerId(): string {
    const rawName = (this.nombre.value ?? '').toString().trim();
    if (!rawName) {
      return '#Nom0000';
    }

    const safeName = rawName
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, '');

    return `#${safeName || 'Nom'}0000`;
  }

  onSubmit() {
    this.error = '';
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.loading = true;
    this.auth.register(this.form.value).subscribe({
      next: (res: any) => {
        this.loading = false;
        this.registeredId = res.user?.player_id ?? '';
        // No redirigim yet — mostrem l'ID real a l'usuari primer
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

