import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chuchemon } from '../../models/chuchemon.model';
import { ChuchemonService } from '../../services/chuchemon.service';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonCardComponent } from '../../components/chuchemon-card/chuchemon-card.component';

interface ChuchemonExtended extends Chuchemon {
  captured?: boolean;
  count?: number;
}

@Component({
  selector: 'app-chuchedex',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, ChuchemonCardComponent],
  templateUrl: './chuchedex.component.html',
  styleUrls: ['./chuchedex.component.css']
})
export class ChuchedexComponent implements OnInit, OnDestroy {
  chuchemons: ChuchemonExtended[] = [];
  myChuchemons: ChuchemonExtended[] = [];
  filteredChuchemons: ChuchemonExtended[] = [];
  selectedElement: 'Todos' | 'Tierra' | 'Aire' | 'Agua' = 'Todos';
  selectedSize: 'Todas' | 'Petit' | 'Mitjà' | 'Gran' = 'Todas';
  selectedTab: 'todos' | 'mis' = 'todos';
  searchQuery: string = '';
  isLoading: boolean = true;
  errorMessage: string | null = null;
  totalChuchemons: number = 0;
  totalCaptured: number = 0;
  completionPercentage: number = 0;
  isAdmin: boolean = false;
  private destroy$ = new Subject<void>();
  private teamChuchemons: Set<number> = new Set();

  constructor(
    private chuchemonService: ChuchemonService,
    private authService: AuthService
  ) { }

  ngOnInit(): void {
    this.checkAdminStatus();
    this.loadChuchemons();
    // Solo cargar mis chuchemons si no es admin
    setTimeout(() => {
      if (!this.isAdmin) {
        this.loadMyChuchemons();
      }
    }, 500);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  checkAdminStatus(): void {
    this.authService.currentUser$
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (user) => {
          this.isAdmin = user?.is_admin ?? false;
        },
        error: (error) => {
          console.error('Error checking admin status:', error);
        }
      });
  }

  loadChuchemons(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.chuchemonService.getAllChuchemons()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.chuchemons = data as ChuchemonExtended[];
          this.totalChuchemons = data.length;
          
          // Contar los capturados
          this.totalCaptured = this.chuchemons.filter(c => c.captured).length;
          
          this.updateCompletionPercentage();
          this.applyFilters();
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Error loading Chuchemons:', error);
          this.errorMessage = 'Error al cargar los Chuchemons';
          this.isLoading = false;
        }
      });
  }

  loadMyChuchemons(): void {
    this.chuchemonService.getMyChuchemons()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.myChuchemons = data as ChuchemonExtended[];
          this.applyFilters();
        },
        error: (error) => {
          console.error('Error loading my Chuchemons:', error);
        }
      });
  }

  updateCompletionPercentage(): void {
    if (this.totalChuchemons > 0) {
      this.completionPercentage = Math.round((this.totalCaptured / this.totalChuchemons) * 100);
    }
  }

  applyFilters(): void {
    let filtered: ChuchemonExtended[] = [];

    // Determinar qué lista usar según la pestaña
    if (this.selectedTab === 'todos') {
      // En "Todos" mostrar TODOS los chuchemons
      filtered = [...this.chuchemons];
    } else {
      // En "Mis Chuchemons" mostrar solo los capturados
      filtered = [...this.myChuchemons];
    }

    // Aplicar filtros de elemento y búsqueda
    if (this.selectedElement !== 'Todos') {
      filtered = filtered.filter(c => c.element === this.selectedElement);
    }

    if (this.selectedSize !== 'Todas') {
      filtered = filtered.filter(c => this.getSizeBadge(c) === this.selectedSize);
    }

    if (this.searchQuery.trim() !== '') {
      filtered = filtered.filter(c => 
        c.name.toLowerCase().includes(this.searchQuery.toLowerCase())
      );
    }

    this.filteredChuchemons = filtered;
  }

  onSizeChange(): void {
    this.applyFilters();
  }

  onElementChange(): void {
    this.applyFilters();
  }

  onTabChange(tab: 'todos' | 'mis'): void {
    this.selectedTab = tab;
    this.applyFilters();
  }

  onSearchChange(): void {
    this.applyFilters();
  }

  getElementColor(element: string): string {
    switch(element) {
      case 'Tierra': return '#d4a574';
      case 'Aire': return '#87ceeb';
      case 'Agua': return '#3b5bdb';
      default: return '#808080';
    }
  }

  isInTeam(chuchemonId: number): boolean {
    return this.teamChuchemons.has(chuchemonId);
  }

  isCaptured(chuchemon: ChuchemonExtended): boolean {
    return chuchemon.captured ?? false;
  }

  isBlockedForDisplay(chuchemon: ChuchemonExtended): boolean {
    // Mostrar bloqueado si: es usuario normal, está en tab "Todos" y no lo ha capturado
    if (this.isAdmin) return false;
    if (this.selectedTab === 'mis') return false;
    return !this.isCaptured(chuchemon);
  }

  getMultiplier(chuchemon: ChuchemonExtended): number {
    return chuchemon.count ?? 1;
  }

  getSizeBadge(chuchemon: ChuchemonExtended): 'Petit' | 'Mitja' | 'Gran' {
    const quantity = this.getMultiplier(chuchemon);
    if (quantity >= 5) return 'Gran';
    if (quantity >= 3) return 'Mitja';
    return 'Petit';
  }

  getQuantityBadge(chuchemon: ChuchemonExtended): string {
    if (!this.isCaptured(chuchemon) && !this.isAdmin && this.selectedTab === 'todos') {
      return 'x?';
    }
    return `x${this.getMultiplier(chuchemon)}`;
  }

  captureChuchemon(chuchemonId: number): void {
    this.chuchemonService.captureChuchemon(chuchemonId)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: () => {
          // Recargar datos
          this.loadChuchemons();
          this.loadMyChuchemons();
        },
        error: (error) => {
          console.error('Error capturing chuchemon:', error);
          this.errorMessage = 'Error al capturar el Chuchemon';
        }
      });
  }

  logout(): void {
    this.authService.logout();
  }
}

