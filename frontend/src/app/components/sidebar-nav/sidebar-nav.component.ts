import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive } from '@angular/router';

@Component({
  selector: 'app-sidebar-nav',
  standalone: true,
  imports: [CommonModule, RouterLink, RouterLinkActive],
  templateUrl: './sidebar-nav.component.html',
  styleUrls: ['./sidebar-nav.component.css']
})
export class SidebarNavComponent {
  @Input() user: any = null;
  @Input() showAdmin = false;
  @Input() showChat = true;
  @Input() pendingRequestsCount = 0;
  @Input() brandName = 'Chuchemons';
  @Input() brandSub = 'Capturalos a todos';
  @Input() showPokeball = true;
  @Input() showUserInfo = true;

  @Output() logoutClick = new EventEmitter<void>();

  onLogout(): void {
    this.logoutClick.emit();
  }
}
