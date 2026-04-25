import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SidebarNavComponent } from '../sidebar-nav/sidebar-nav.component';
import { MobileTopbarComponent } from '../mobile-topbar/mobile-topbar.component';

/**
 * MainLayoutComponent - Layout centralizado para todas las páginas principales
 * 
 * Proporciona:
 * - Mobile topbar fijo superior (solo móvil/tablet)
 * - Sidebar navegación (desktop) o bottom nav (móvil)
 * - Content area con spacing automático responsive
 * 
 * Uso:
 * <app-main-layout [user]="user" (logoutClick)="logout()">
 *   <!-- Tu contenido aquí -->
 * </app-main-layout>
 */
@Component({
  selector: 'app-main-layout',
  standalone: true,
  imports: [CommonModule, SidebarNavComponent, MobileTopbarComponent],
  templateUrl: './main-layout.component.html',
  styleUrls: ['./main-layout.component.css']
})
export class MainLayoutComponent {
  @Input() user: any = null;
  @Input() showAdmin: boolean = false;
  @Input() brandName: string = 'Chuchemons';
  @Input() brandSub: string = '';
  @Input() showPokeball: boolean = true;
  
  @Output() logoutClick = new EventEmitter<void>();

  onLogout() {
    this.logoutClick.emit();
  }
}
