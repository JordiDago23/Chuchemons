import { Component, OnInit, OnDestroy, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription, interval } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chuchemon } from '../../models/chuchemon.model';
import { ChuchemonService } from '../../services/chuchemon.service';
import { EvolutionService } from '../../services/evolution.service';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonCardComponent } from '../../components/chuchemon-card/chuchemon-card.component';
import { ConfirmDialogComponent } from '../../components/dialogs/confirm-dialog.component';
import { ChuchemonDetailsModalComponent } from '../../components/chuchemon-details-modal/chuchemon-details-modal.component';
import { MainLayoutComponent } from '../../components/main-layout/main-layout.component';

interface ChuchemonExtended extends Chuchemon {
  captured?: boolean;
  count?: number;
}

type ElementFilter = 'Todos' | 'Terra' | 'Aire' | 'Aigua';

@Component({
  selector: 'app-chuchedex',
  standalone: true,
  imports: [CommonModule, FormsModule, ChuchemonCardComponent, ConfirmDialogComponent, ChuchemonDetailsModalComponent, MainLayoutComponent],
  templateUrl: './chuchedex.component.html',
  styleUrls: ['./chuchedex.component.css']
})
export class ChuchedexComponent implements OnInit, OnDestroy {
  chuchemons: ChuchemonExtended[] = [];
  myChuchemons: ChuchemonExtended[] = [];
  filteredChuchemons: ChuchemonExtended[] = [];
  selectedElement: ElementFilter = 'Todos';
  selectedSize: 'Todas' | 'Petit' | 'Mitjà' | 'Gran' = 'Todas';
  selectedTab: 'todos' | 'mis' = 'todos';
  searchQuery: string = '';
  isLoading: boolean = true;
  errorMessage: string | null = null;
  totalChuchemons: number = 0;
  totalCaptured: number = 0;
  completionPercentage: number = 0;
  isAdmin: boolean = false;
  user: any = null;
  private destroy$ = new Subject<void>();
  private teamChuchemons: Set<number> = new Set();

  // Evolution dialog properties
  showEvolutionDialog = false;
  evolvingChuchemonId: number | null = null;
  evolvingChuchemonName = '';
  evolvingChuchemonNextMida = '';
  showEvolutionCelebration = false;
  evolutionCelebrationName = '';
  evolutionCelebrationSize = '';
  private evolutionCelebrationTimer: ReturnType<typeof setTimeout> | null = null;

  // Details modal properties
  showDetailsModal = false;
  selectedChuchemonForDetails: ChuchemonExtended | null = null;

  private pageVisible = true;
  private autoRefreshSubscription?: Subscription;
  private readonly visibilityChangeHandler = () => {
    if (!document.hidden) {
      this.loadChuchemons(false);
    }
  };

  constructor(
    private chuchemonService: ChuchemonService,
    private evolutionService: EvolutionService,
    private authService: AuthService
  ) { }

  ngOnInit(): void {
    this.checkAdminStatus();
    this.loadChuchemons();
    this.chuchemonService.stateChanges$
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        if (!this.isLoading) {
          this.loadChuchemons(false, true);
        }
      });
    
    // Auto-refresh every 10 seconds when page is visible
    this.setupAutoRefresh();
    
    // Reload when page/tab becomes visible
    document.addEventListener('visibilitychange', this.visibilityChangeHandler);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    document.removeEventListener('visibilitychange', this.visibilityChangeHandler);
    if (this.autoRefreshSubscription) {
      this.autoRefreshSubscription.unsubscribe();
    }
    if (this.evolutionCelebrationTimer) {
      clearTimeout(this.evolutionCelebrationTimer);
    }
  }

  private setupAutoRefresh(): void {
    // Auto-refresh every 10 seconds
    this.autoRefreshSubscription = interval(10000)
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        if (!this.isLoading && !document.hidden) {
          this.loadChuchemons(false, true);
        }
      });
  }

  @HostListener('window:focus', ['$event'])
  onWindowFocus(event: any): void {
    // Reload when window regains focus
    this.loadChuchemons(false, true);
  }

  checkAdminStatus(): void {
    this.authService.currentUser$
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (user) => {
          this.user = user;
          this.isAdmin = user?.is_admin ?? false;
        },
        error: (error) => {
          console.error('Error checking admin status:', error);
        }
      });
  }

  loadChuchemons(showLoader: boolean = true, forceRefresh: boolean = false): void {
    if (showLoader) {
      this.isLoading = true;
      this.errorMessage = null;
    }

    this.chuchemonService.getAllChuchemons(forceRefresh)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.chuchemons = data as ChuchemonExtended[];
          this.myChuchemons = this.isAdmin
            ? []
            : this.chuchemons.filter(c => c.captured);
          this.totalChuchemons = data.length;
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
      filtered = filtered.filter(c => this.normalizeElement(c.element) === this.selectedElement);
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
    switch (this.normalizeElement(element)) {
      case 'Terra': return '#d4a574';
      case 'Aire': return '#87ceeb';
      case 'Aigua': return '#3b5bdb';
      default: return '#808080';
    }
  }

  private normalizeElement(element?: string | null): 'Terra' | 'Aire' | 'Aigua' | '' {
    switch (element) {
      case 'Terra':
      case 'Tierra':
        return 'Terra';
      case 'Aigua':
      case 'Agua':
        return 'Aigua';
      case 'Aire':
        return 'Aire';
      default:
        return '';
    }
  }

  getSizeLabel(size: string): string {
    switch (size) {
      case 'Petit': return 'Pequeño';
      case 'Mitjà': return 'Mediano';
      case 'Gran': return 'Grande';
      default: return size;
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

  getSizeBadge(chuchemon: ChuchemonExtended): 'Petit' | 'Mitjà' | 'Gran' {
    if (chuchemon.current_mida === 'Petit' || chuchemon.current_mida === 'Mitjà' || chuchemon.current_mida === 'Gran') {
      return chuchemon.current_mida;
    }

    const quantity = this.getMultiplier(chuchemon);
    if (quantity >= 5) return 'Gran';
    if (quantity >= 3) return 'Mitjà';
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
          this.chuchemonService.invalidateCaches();
          this.loadChuchemons(true, true);
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
          this.triggerEvolutionCelebration(
            response.chuchemon?.name ?? this.evolvingChuchemonName,
            response.chuchemon?.current_mida ?? this.evolvingChuchemonNextMida
          );
          this.showEvolutionDialog = false;
          this.evolvingChuchemonId = null;
          // Recargar datos
          this.chuchemonService.invalidateCaches();
          this.loadChuchemons(true, true);
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

  openDetailsModal(chuchemonId: number): void {
    // Buscar en todos los arrays posibles
    let chuchemon = this.chuchemons.find(c => c.id === chuchemonId);
    if (!chuchemon) {
      chuchemon = this.myChuchemons.find(c => c.id === chuchemonId);
    }
    if (!chuchemon) {
      chuchemon = this.filteredChuchemons.find(c => c.id === chuchemonId);
    }

    if (chuchemon) {
      this.selectedChuchemonForDetails = chuchemon;
      this.showDetailsModal = true;
    } else {
      console.error('Chuchemon not found with ID:', chuchemonId);
    }
  }

  closeDetailsModal(): void {
    this.showDetailsModal = false;
    this.selectedChuchemonForDetails = null;
  }

  private triggerEvolutionCelebration(name: string, nextMida: string): void {
    this.evolutionCelebrationName = name;
    this.evolutionCelebrationSize = this.getSizeLabel(nextMida);
    this.showEvolutionCelebration = true;

    if (this.evolutionCelebrationTimer) {
      clearTimeout(this.evolutionCelebrationTimer);
    }

    this.evolutionCelebrationTimer = setTimeout(() => {
      this.showEvolutionCelebration = false;
      this.evolutionCelebrationName = '';
      this.evolutionCelebrationSize = '';
      this.evolutionCelebrationTimer = null;
    }, 1800);
  }

  logout(): void {
    this.authService.logout();
  }
}


