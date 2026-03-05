import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chuchemon } from '../../models/chuchemon.model';
import { ChuchemonService } from '../../services/chuchemon.service';

@Component({
  selector: 'app-chuchedex',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './chuchedex.component.html',
  styleUrls: ['./chuchedex.component.css']
})
export class ChuchedexComponent implements OnInit, OnDestroy {
  chuchemons: Chuchemon[] = [];
  filteredChuchemons: Chuchemon[] = [];
  selectedElement: 'Todos' | 'Tierra' | 'Aire' | 'Agua' = 'Todos';
  searchQuery: string = '';
  isLoading: boolean = true;
  errorMessage: string | null = null;
  private destroy$ = new Subject<void>();

  constructor(private chuchemonService: ChuchemonService) { }

  ngOnInit(): void {
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
      case 'Tierra': return '#8B7355';
      case 'Aire': return '#87CEEB';
      case 'Agua': return '#4169E1';
      default: return '#808080';
    }
  }
}
