import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { interval, Subscription } from 'rxjs';

@Component({
  selector: 'app-infections-panel',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './infections-panel.component.html',
  styleUrls: ['./infections-panel.component.css']
})
export class InfectionsPanelComponent implements OnInit, OnDestroy {
  infections: any[] = [];
  malalties: any[] = [];
  vaccines: any[] = [];
  todos: any[] = [];
  isLoading = false;
  errorMessage: string | null = null;
  private pollingSubscription?: Subscription;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadInfections();
    this.loadMalalties();
    this.loadVaccines();
    this.startPolling();
  }

  ngOnDestroy(): void {
    this.stopPolling();
  }

  private startPolling(): void {
    // Verificar cambios cada 30 segundos
    this.pollingSubscription = interval(30000).subscribe(() => {
      this.loadInfections();
      this.loadVaccines();
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
}
