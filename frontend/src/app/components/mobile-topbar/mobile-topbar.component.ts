import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-mobile-topbar',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './mobile-topbar.component.html',
  styleUrls: ['./mobile-topbar.component.css']
})
export class MobileTopbarComponent {
  @Input() user: any = null;
  @Input() brandName = 'Chuchemons';

  @Output() logoutClick = new EventEmitter<void>();

  onLogout(): void {
    this.logoutClick.emit();
  }

  getAvatarText(): string {
    if (!this.user) return '?';
    if (this.user.nombre) {
      return this.user.nombre.substring(0, 2).toUpperCase();
    }
    if (this.user.player_id) {
      return this.user.player_id.substring(0, 2).toUpperCase();
    }
    return '?';
  }
}
