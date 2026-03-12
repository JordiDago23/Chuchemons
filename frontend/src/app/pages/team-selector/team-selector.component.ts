import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { Chuchemon } from '../../models/chuchemon.model';
import { ChuchemonService } from '../../services/chuchemon.service';

interface ChuchemonExtended extends Chuchemon {
  count?: number;
}

@Component({
  selector: 'app-team-selector',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './team-selector.component.html',
  styleUrls: ['./team-selector.component.css']
})
export class TeamSelectorComponent implements OnInit, OnDestroy {
  myChuchemons: ChuchemonExtended[] = [];
  selectedChuchemons: (number | null)[] = [null, null, null];
  isLoading: boolean = true;
  errorMessage: string | null = null;
  successMessage: string | null = null;
  private destroy$ = new Subject<void>();

  constructor(
    private chuchemonService: ChuchemonService,
    private router: Router
  ) { }

  ngOnInit(): void {
    this.loadMyChuchemons();
    this.loadCurrentTeam();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadMyChuchemons(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.chuchemonService.getMyChuchemons()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.myChuchemons = data as ChuchemonExtended[];
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Error loading my Chuchemons:', error);
          this.errorMessage = 'Error al cargar tus Chuchemons';
          this.isLoading = false;
        }
      });
  }

  loadCurrentTeam(): void {
    this.chuchemonService.getTeam()
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (response) => {
          if (response.team_ids) {
            this.selectedChuchemons = [...response.team_ids];
          }
        },
        error: (error) => {
          console.error('Error loading team:', error);
        }
      });
  }

  selectChuchemon(position: number, chuchemonId: number | null): void {
    this.selectedChuchemons[position] = chuchemonId;
  }

  getChuchemonImage(position: number): string {
    const id = this.selectedChuchemons[position];
    if (id == null) return 'placeholder.png';
    const chuchemon = this.myChuchemons.find(c => c.id === id);
    return chuchemon?.image ?? 'placeholder.png';
  }

  getSelectedChuchemonName(chuchemonId: number | null): string {
    if (chuchemonId === null) {
      return 'Seleccionar...';
    }
    const chuchemon = this.myChuchemons.find(c => c.id === chuchemonId);
    return chuchemon ? chuchemon.name : 'Desconocido';
  }

  getMultiplier(chuchemonId: number | null): number {
    if (chuchemonId === null) {
      return 0;
    }
    const chuchemon = this.myChuchemons.find(c => c.id === chuchemonId);
    return chuchemon ? (chuchemon.count ?? 1) : 0;
  }

  saveTeam(): void {
    this.chuchemonService.saveTeam(
      this.selectedChuchemons[0],
      this.selectedChuchemons[1],
      this.selectedChuchemons[2]
    )
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: () => {
          this.successMessage = 'Equipo guardado exitosamente';
          setTimeout(() => {
            this.router.navigate(['/chuchedex']);
          }, 1500);
        },
        error: (error) => {
          console.error('Error saving team:', error);
          this.errorMessage = 'Error al guardar el equipo';
        }
      });
  }

  goBack(): void {
    this.router.navigate(['/chuchedex']);
  }
}
