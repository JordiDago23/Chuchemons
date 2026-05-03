import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { InfectionsService } from '../../services/infections.service';
import { LevelingService } from '../../services/leveling.service';

@Component({
  selector: 'app-infections-panel',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './infections-panel.component.html',
  styleUrls: ['./infections-panel.component.css']
})
export class InfectionsPanelComponent implements OnInit, OnDestroy {
  infections: any[] = [];
  malalties: any[] = [];
  vaccines: any[] = [];
  todos: any[] = [];
  teamChuchemons: any[] = [];
  lowHpChuchemons: any[] = [];
  healQtyMap: Record<number, number> = {};
  healingMap: Record<number, boolean> = {};
  evolvingMap: Record<number, boolean> = {};
  feedbackMap: Record<number, { type: string; msg: string } | null> = {};
  isLoading = false;
  errorMessage: string | null = null;
  private destroy$ = new Subject<void>();
  private readonly api = 'http://localhost:8000/api';

  constructor(
    private http: HttpClient,
    private infectionsService: InfectionsService,
    private levelingService: LevelingService
  ) {}

  ngOnInit(): void {
    // Cargar datos inicialmente
    this.infectionsService.refreshAll();
    this.levelingService.refreshLevelingChuchemons();

    // Suscribirse a infections reactivas
    this.infectionsService.infections$
      .pipe(takeUntil(this.destroy$))
      .subscribe(infections => {
        this.infections = infections;
        this.generateTodos();
      });

    // Suscribirse a malalties reactivas
    this.infectionsService.malalties$
      .pipe(takeUntil(this.destroy$))
      .subscribe(malalties => {
        this.malalties = malalties;
        this.generateTodos();
      });

    // Suscribirse a vaccines reactivas
    this.infectionsService.vaccines$
      .pipe(takeUntil(this.destroy$))
      .subscribe(vaccines => {
        this.vaccines = vaccines;
        this.generateTodos();
      });

    // Suscribirse a team chuchemons reactivos
    this.levelingService.levelingChuchemons$
      .pipe(takeUntil(this.destroy$))
      .subscribe(chuchemons => {
        this.teamChuchemons = chuchemons;
        this.lowHpChuchemons = chuchemons.filter((c: any) => {
          const curr = c.current_hp ?? c.max_hp;
          const max = c.max_hp ?? 1;
          return curr < max;
        });
        // Init heal qty map
        chuchemons.forEach((c: any) => {
          if (this.healQtyMap[c.id] === undefined) this.healQtyMap[c.id] = 1;
        });
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  generateTodos(): void {
    this.todos = [];

    // Todo: Curar infecciones activas
    this.infections.forEach(infection => {
      this.todos.push({
        id: `cure-${infection.id}`,
        type: 'cure',
        priority: 'high',
        title: `Curar infección de ${infection.chuchemon?.name}`,
        description: `${infection.chuchemon?.name} está infectado con ${infection.malaltia?.name}`,
        action: 'Usar vacuna disponible'
      });
    });

    // Todo: Vacunar chuchemons sanos (preventivo)
    // Nota: Esto requeriría una API para obtener chuchemons del usuario
    // Por ahora, solo agregamos un recordatorio general
    if (this.infections.length === 0 && this.vaccines.length > 0) {
      this.todos.push({
        id: 'preventive-vaccination',
        type: 'vaccination',
        priority: 'medium',
        title: 'Vacunación preventiva',
        description: 'Considera vacunar a tus chuchemons para prevenir enfermedades',
        action: 'Revisar vacunas disponibles'
      });
    }

    // Todo: Verificar stock de vacunas
    const lowStockVaccines = this.vaccines.filter(v => v.stock && v.stock < 5);
    lowStockVaccines.forEach(vaccine => {
      this.todos.push({
        id: `restock-${vaccine.id}`,
        type: 'restock',
        priority: 'low',
        title: `Reponer stock de ${vaccine.name}`,
        description: `Quedan ${vaccine.stock} unidades de ${vaccine.name}`,
        action: 'Contactar administrador'
      });
    });
  }

  loadInfections(): void {
    this.infectionsService.refreshInfections(true);
  }

  loadMalalties(): void {
    this.infectionsService.refreshMalalties(true);
  }

  loadVaccines(): void {
    this.infectionsService.refreshVaccines(true);
  }

  cureInfection(infectionId: number, vaccineId: number): void {
    this.infectionsService.cureInfection(infectionId, vaccineId).subscribe({
      next: () => {
        this.errorMessage = null;
      },
      error: (error) => {
        console.error('Error curing infection:', error);
        this.errorMessage = 'Error curando la infección';
      }
    });
  }

  loadTeamHp(): void {
    this.levelingService.refreshLevelingChuchemons(true);
  }

  healChuchemon(c: any): void {
    const qty = this.healQtyMap[c.id] ?? 1;
    if (qty < 1 || this.healingMap[c.id]) return;
    this.healingMap[c.id] = true;
    this.feedbackMap[c.id] = null;
    this.levelingService.healChuchemon(c.id, qty).subscribe({
      next: (res) => {
        this.feedbackMap[c.id] = { type: 'success', msg: res.message };
        this.healingMap[c.id] = false;
      },
      error: (err) => {
        this.feedbackMap[c.id] = { type: 'error', msg: err.error?.message ?? 'Error al curar' };
        this.healingMap[c.id] = false;
      }
    });
  }

  evolveChuchemon(c: any): void {
    if (this.evolvingMap[c.id]) return;
    this.evolvingMap[c.id] = true;
    this.feedbackMap[c.id] = null;
    this.levelingService.evolveChuchemon(c.id).subscribe({
      next: (res) => {
        this.feedbackMap[c.id] = { type: 'success', msg: res.message };
        this.evolvingMap[c.id] = false;
      },
      error: (err) => {
        this.feedbackMap[c.id] = { type: 'error', msg: err.error?.message ?? 'Error al evolucionar' };
        this.evolvingMap[c.id] = false;
      }
    });
  }

  getSizeLabel(mida: string): string {
    return mida === 'Petit' ? 'Pequeño' : mida === 'Mitjà' ? 'Mediano' : mida === 'Gran' ? 'Grande' : mida;
  }

  getNextSize(mida: string): string {
    return mida === 'Petit' ? 'Mediano' : mida === 'Mitjà' ? 'Grande' : '';
  }

  getEvolveCost(c: any): number {
    const base = c.current_mida === 'Petit' ? 3 : c.current_mida === 'Mitjà' ? 5 : 0;
    return base + (c.evolve_cost_extra ?? 0);
  }
}
