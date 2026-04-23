import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { Subject, interval, Subscription } from 'rxjs';
import { takeUntil, finalize } from 'rxjs/operators';
import { Chuchemon } from '../../models/chuchemon.model';
import { ChuchemonService } from '../../services/chuchemon.service';
import { AuthService } from '../../core/services/auth.service';
import { SidebarNavComponent } from '../../components/sidebar-nav/sidebar-nav.component';

interface ChuchemonExtended extends Chuchemon {
  count?: number;
}

@Component({
  selector: 'app-team-selector',
  standalone: true,
  imports: [CommonModule, SidebarNavComponent],
  templateUrl: './team-selector.component.html',
  styleUrls: ['./team-selector.component.css']
})
export class TeamSelectorComponent implements OnInit, OnDestroy {
  myChuchemons: ChuchemonExtended[] = [];
  selectedChuchemons: (number | null)[] = [null, null, null];
  isLoading: boolean = true;
  user: any = null;
  errorMessage: string | null = null;
  successMessage: string | null = null;
  private destroy$ = new Subject<void>();
  private pollingSubscription?: Subscription;

  constructor(
    private chuchemonService: ChuchemonService,
    private router: Router,
    private auth: AuthService,
    private cdRef: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    this.auth.currentUser$.pipe(takeUntil(this.destroy$)).subscribe(u => this.user = u);
    this.loadMyChuchemons();
    this.loadCurrentTeam();
    this.startPolling();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.stopPolling();
  }

  private startPolling(): void {
    // Verificar cambios cada 30 segundos
    this.pollingSubscription = interval(30000).subscribe(() => {
      this.loadMyChuchemons();
      this.loadCurrentTeam();
    });
  }

  private stopPolling(): void {
    this.pollingSubscription?.unsubscribe();
  }

  loadMyChuchemons(): void {
    this.isLoading = true;
    this.errorMessage = null;

    this.chuchemonService.getMyChuchemons()
      .pipe(
        takeUntil(this.destroy$),
        finalize(() => { this.isLoading = false; })
      )
      .subscribe({
        next: (data) => {
          this.myChuchemons = Array.isArray(data) ? data as ChuchemonExtended[] : [];
          this.cdRef.detectChanges();
        },
        error: (error) => {
          console.error('Error loading my Chuchemons:', error);
          this.errorMessage = 'Error al cargar tus Chuchemons';
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
          this.cdRef.detectChanges();
        },
        error: (error) => {
          console.error('Error loading team:', error);
          this.cdRef.detectChanges();
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

  logout(): void {
    this.auth.logout();
  }
}

