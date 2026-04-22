import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  ReactiveFormsModule, FormBuilder, FormGroup,
  Validators, AbstractControl, ValidationErrors
} from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

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
  styleUrls: ['./register.component.css']
})
export class RegisterComponent {
  form: FormGroup;
  error = '';
  loading = false;
  registeredId = '';  // ID real generat pel backend
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
