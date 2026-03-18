import { Component, OnInit, OnDestroy, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Subject, interval } from 'rxjs';
import { takeUntil, switchMap } from 'rxjs/operators';
import { Chuchemon } from '../../models/chuchemon.model';
import { ChuchemonService } from '../../services/chuchemon.service';
import { EvolutionService } from '../../services/evolution.service';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonCardComponent } from '../../components/chuchemon-card/chuchemon-card.component';
import { ConfirmDialogComponent } from '../../components/dialogs/confirm-dialog.component';

interface ChuchemonExtended extends Chuchemon {
  captured?: boolean;
  count?: number;
}

@Component({
  selector: 'app-chuchedex',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, ChuchemonCardComponent, ConfirmDialogComponent],
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

  // Evolution dialog properties
  showEvolutionDialog = false;
  evolvingChuchemonId: number | null = null;
  evolvingChuchemonName = '';
  evolvingChuchemonNextMida = '';

  private pageVisible = true;
  private autoRefreshSubscription: any;

  constructor(
    private chuchemonService: ChuchemonService,
    private evolutionService: EvolutionService,
    private authService: AuthService
  ) { }

  ngOnInit(): void {
    this.checkAdminStatus();
    this.loadChuchemons();
    
    // Auto-refresh every 10 seconds when page is visible
    this.setupAutoRefresh();
    
    // Reload when page/tab becomes visible
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) {
        this.loadChuchemons();
      }
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.autoRefreshSubscription) {
      this.autoRefreshSubscription.unsubscribe();
    }
  }

  private setupAutoRefresh(): void {
    // Auto-refresh every 10 seconds
    this.autoRefreshSubscription = interval(10000)
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        if (!this.isLoading && !document.hidden) {
          this.loadChuchemons();
        }
      });
  }

  @HostListener('window:focus', ['$event'])
  onWindowFocus(event: any): void {
    // Reload when window regains focus
    this.loadChuchemons();
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
          
          // Reload my chuchemons to sync captured status
          if (!this.isAdmin) {
            this.loadMyChuchemons();
          } else {
            this.applyFilters();
            this.isLoading = false;
          }
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
          // Update captured status in all chuchemons list
          const myIds = new Set(this.myChuchemons.map(c => c.id));
          this.chuchemons.forEach(c => c.captured = myIds.has(c.id));
          this.totalCaptured = this.chuchemons.filter(c => c.captured).length;
          this.updateCompletionPercentage();
          this.applyFilters();
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Error loading my Chuchemons:', error);
          this.isLoading = false;
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
    // Reload all data when switching tabs to sync with potential admin changes
    if (!this.isAdmin) {
      this.isLoading = true;
      this.loadChuchemons();
    } else {
      this.applyFilters();
    }
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

  openEvolutionDialog(chuchemonId: number, chuchemonName: string): void {
    this.evolutionService.getEvolutionInfo(chuchemonId)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (info) => {
          if (info.can_evolve) {
            this.evolvingChuchemonId = chuchemonId;
            this.evolvingChuchemonName = chuchemonName;
            this.evolvingChuchemonNextMida = info.next_mida || '';
            this.showEvolutionDialog = true;
          } else {
            alert('Este Xuxemon ya está en su máxima evolución.');
          }
        },
        error: (error) => {
          console.error('Error getting evolution info:', error);
          alert('Error al obtener la información de evolución');
        }
      });
  }

  confirmEvolution(): void {
    if (this.evolvingChuchemonId === null) return;

    this.evolutionService.evolveChuchemon(this.evolvingChuchemonId)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          alert(response.message);
          this.showEvolutionDialog = false;
          this.evolvingChuchemonId = null;
          // Recargar datos
          this.loadChuchemons();
          this.loadMyChuchemons();
        },
        error: (error) => {
          console.error('Error evolving chuchemon:', error);
          alert('Error al evolucionar el Xuxemon');
        }
      });
  }

  cancelEvolution(): void {
    this.showEvolutionDialog = false;
    this.evolvingChuchemonId = null;
  }

  logout(): void {
    this.authService.logout();
  }
}

