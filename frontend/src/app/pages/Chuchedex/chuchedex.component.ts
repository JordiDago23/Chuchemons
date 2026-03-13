import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chuchemon } from '../../models/chuchemon.model';
import { ChuchemonService } from '../../services/chuchemon.service';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-chuchedex',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './chuchedex.component.html',
  styleUrls: ['./chuchedex.component.css']
})
export class ChuchedexComponent implements OnInit, OnDestroy {
  user: any = null;
  chuchemons: Chuchemon[] = [];
  filteredChuchemons: Chuchemon[] = [];
  selectedElement: 'Todos' | 'Tierra' | 'Aire' | 'Agua' = 'Todos';
  searchQuery: string = '';
  isLoading: boolean = true;
  errorMessage: string | null = null;
  totalChuchemons: number = 0;
  totalCaptured: number = 0;
  completionPercentage: number = 0;
  private destroy$ = new Subject<void>();
  private capturedChuchemons: Set<number> = new Set();
  private teamChuchemons: Set<number> = new Set();

  constructor(private chuchemonService: ChuchemonService, private auth: AuthService) { }

  ngOnInit(): void {
    this.user = this.auth.currentUser;
    if (!this.user) {
      this.auth.me().subscribe({ next: (data) => this.user = data });
    }
    this.loadCapturedChuchemons();
    this.loadChuchemons();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadChuchemons(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.chuchemonService.getAllChuchemons()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.chuchemons = data;
          this.totalChuchemons = data.length;
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

  loadCapturedChuchemons(): void {
    // Simular chuchemons capturados - TODOS los 48
    const capturedIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48];
    this.capturedChuchemons = new Set(capturedIds);
    this.totalCaptured = capturedIds.length;
    
    // Solo 3 en el equipo - seleccionados por el usuario
    const teamIds = [1, 2, 3];
    this.teamChuchemons = new Set(teamIds);
  }

  updateCompletionPercentage(): void {
    if (this.totalChuchemons > 0) {
      this.completionPercentage = Math.round((this.totalCaptured / this.totalChuchemons) * 100);
    }
  }

  applyFilters(): void {
    let filtered = this.chuchemons;

    if (this.selectedElement !== 'Todos') {
      filtered = filtered.filter(c => c.element === this.selectedElement);
    }

    if (this.searchQuery.trim() !== '') {
      filtered = filtered.filter(c => 
        c.name.toLowerCase().includes(this.searchQuery.toLowerCase())
      );
    }

    this.filteredChuchemons = filtered;
  }

  onElementChange(): void {
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

  logout(): void {
    this.auth.logout();
  }
}
