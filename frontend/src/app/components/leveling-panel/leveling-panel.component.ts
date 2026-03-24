import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Component({
  selector: 'app-leveling-panel',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './leveling-panel.component.html',
  styleUrls: ['./leveling-panel.component.css']
})
export class LevelingPanelComponent implements OnInit {
  chuchemons: any[] = [];
  selectedChuchemon: any = null;
  isLoading = false;
  errorMessage: string | null = null;

  constructor(private http: HttpClient) {}

  ngOnInit(): void {
    this.loadChuchemonsWithLevels();
  }

  loadChuchemonsWithLevels(): void {
    this.isLoading = true;
    this.http.get<any[]>('http://localhost:8000/api/level/chuchemons').subscribe({
      next: (data) => {
        this.chuchemons = data;
        if (data.length > 0) {
          this.selectedChuchemon = data[0];
        }
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading chuchemons:', error);
        this.errorMessage = 'Error cargando chuchemons';
        this.isLoading = false;
      }
    });
  }

  selectChuchemon(chuchemon: any): void {
    this.selectedChuchemon = chuchemon;
  }

  addExperience(amount: number): void {
    if (!this.selectedChuchemon) return;
    this.http.post(`http://localhost:8000/api/level/chuchemon/${this.selectedChuchemon.id}/add-experience/${amount}`, {}).subscribe({
      next: (response: any) => {
        this.selectedChuchemon = response;
        this.loadChuchemonsWithLevels();
      },
      error: (error) => {
        console.error('Error adding experience:', error);
        this.errorMessage = 'Error añadiendo experiencia';
      }
    });
  }
}
