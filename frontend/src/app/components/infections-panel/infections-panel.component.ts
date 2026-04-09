import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { interval, Subscription } from 'rxjs';

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
  private pollingSubscription?: Subscription;
  private readonly api = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadInfections();
    this.loadMalalties();
    this.loadVaccines();
    this.loadTeamHp();
    this.startPolling();
  }

  ngOnDestroy(): void {
    this.stopPolling();
  }

  private startPolling(): void {
    this.pollingSubscription = interval(30000).subscribe(() => {
      this.loadInfections();
      this.loadVaccines();
      this.loadTeamHp();
    });
  }

  private stopPolling(): void {
    this.pollingSubscription?.unsubscribe();
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
        description: `${infection.chuchemon?.name} está infectado con ${infection.malaltia?.name} (${infection.infection_percentage}% infectado)`,
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
    this.http.get<any[]>('http://localhost:8000/api/infections').subscribe({
      next: (data) => {
        this.infections = data;
        this.generateTodos();
      },
      error: (error) => {
        console.error('Error loading infections:', error);
      }
    });
  }

  loadMalalties(): void {
    this.http.get<any[]>('http://localhost:8000/api/malalties').subscribe({
      next: (data) => {
        this.malalties = data;
        this.generateTodos();
      },
      error: (error) => {
        console.error('Error loading malalties:', error);
      }
    });
  }

  loadVaccines(): void {
    this.http.get<any[]>('http://localhost:8000/api/vaccines').subscribe({
      next: (data) => {
        this.vaccines = data;
        this.generateTodos();
      },
      error: (error) => {
        console.error('Error loading vaccines:', error);
      }
    });
  }

  cureInfection(infectionId: number, vaccineId: number): void {
    this.http.post(`http://localhost:8000/api/infections/cure/${infectionId}/${vaccineId}`, {}).subscribe({
      next: () => {
        this.loadInfections();
        this.errorMessage = null;
      },
      error: (error) => {
        console.error('Error curing infection:', error);
        this.errorMessage = 'Error curando la infección';
      }
    });
  }

  loadTeamHp(): void {
    this.http.get<any[]>(`${this.api}/level/chuchemons`).subscribe({
      next: (data) => {
        this.teamChuchemons = data;
        this.lowHpChuchemons = data.filter(c => {
          const curr = c.current_hp ?? c.max_hp;
          const max = c.max_hp ?? 1;
          return curr < max;
        });
        // Init heal qty map
        data.forEach(c => {
          if (this.healQtyMap[c.id] === undefined) this.healQtyMap[c.id] = 1;
        });
      },
      error: () => {}
    });
  }

  healChuchemon(c: any): void {
    const qty = this.healQtyMap[c.id] ?? 1;
    if (qty < 1 || this.healingMap[c.id]) return;
    this.healingMap[c.id] = true;
    this.feedbackMap[c.id] = null;
    this.http.post<any>(`${this.api}/user/chuchemons/${c.id}/heal`, { quantity: qty }).subscribe({
      next: (res) => {
        this.feedbackMap[c.id] = { type: 'success', msg: res.message };
        this.healingMap[c.id] = false;
        this.loadTeamHp();
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
    this.http.post<any>(`${this.api}/chuchemons/${c.id}/evolve`, {}).subscribe({
      next: (res) => {
        this.feedbackMap[c.id] = { type: 'success', msg: res.message };
        this.evolvingMap[c.id] = false;
        this.loadTeamHp();
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
