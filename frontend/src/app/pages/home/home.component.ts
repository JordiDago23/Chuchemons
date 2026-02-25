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

  constructor(private auth: AuthService) {}

  ngOnInit() {
    this.auth.me().subscribe({
      next: (data) => this.user = data,
      error: () => this.auth.logout()
    });
  }

  logout() {
    this.auth.logout();
  }
}