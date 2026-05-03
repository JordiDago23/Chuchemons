import { Component, Input, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { ConfirmDialogComponent } from '../dialogs/confirm-dialog.component';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonService } from '../../services/chuchemon.service';
import { ConfigService, EvolutionConfig } from '../../services/config.service';
import { LevelingService } from '../../services/leveling.service';

@Component({
  selector: 'app-leveling-panel',
  standalone: true,
  imports: [CommonModule, FormsModule, ConfirmDialogComponent],
  templateUrl: './leveling-panel.component.html',
  styleUrls: ['./leveling-panel.component.css']
})
export class LevelingPanelComponent implements OnInit, OnDestroy {
  @Input() compact = false;
  chuchemons: any[] = [];
  selectedChuchemon: any = null;
  isLoading = false;
  errorMessage: string | null = null;
  actionMessage: string | null = null;
  healQty = 1;
  showActionConfirm = false;
  confirmTitle = '';
  confirmMessage = '';
  private confirmAction: (() => void) | null = null;
  private destroy$ = new Subject<void>();

  // Evolution animation overlay
  showEvoOverlay = false;
  evoName = '';
  evoSize = '';
  private evoTimer: ReturnType<typeof setTimeout> | null = null;

  // Configuración de costos de evolución (actualizada reactivamente)
  evolveCostConfig: EvolutionConfig = {
    xux_petit_mitja: 3,
    xux_mitja_gran: 5
  };

  private readonly api = 'http://localhost:8000/api';

  constructor(
    private http: HttpClient,
    private auth: AuthService,
    private chuchemonService: ChuchemonService,
    private configService: ConfigService,
    private levelingService: LevelingService
  ) {}

  ngOnInit(): void {
    // Cargar datos inicialmente
    this.levelingService.refreshLevelingChuchemons();
    
    // Suscribirse a cambios reactivos de chuchemons
    this.levelingService.levelingChuchemons$
      .pipe(takeUntil(this.destroy$))
      .subscribe(chuchemons => {
        this.chuchemons = chuchemons;
        if (chuchemons.length > 0) {
          const prevId = this.selectedChuchemon?.id;
          this.selectedChuchemon = chuchemons.find((c: any) => c.id === prevId) ?? chuchemons[0];
        }
        this.isLoading = false;
      });
    
    // Suscribirse a cambios de estado para actualizar automáticamente
    this.chuchemonService.stateChanges$
      .pipe(takeUntil(this.destroy$))
      .subscribe(() => {
        this.levelingService.notifyStateChanged();
      });

    // Suscribirse a actualizaciones reactivas de configuración de evolución
    this.configService.evolutionConfig$
      .pipe(takeUntil(this.destroy$))
      .subscribe(config => {
        this.evolveCostConfig = config;
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    if (this.evoTimer) clearTimeout(this.evoTimer);
  }

  loadChuchemons(): void {
    // Ahora delegado al servicio reactivo
    this.isLoading = true;
    this.levelingService.refreshLevelingChuchemons(true);
  }

  selectChuchemon(c: any): void {
    this.selectedChuchemon = c;
    this.actionMessage = null;
  }

  evolve(): void {
    if (!this.selectedChuchemon) return;
    const cost = this.getEvolveCost();
    const currentXp = this.selectedChuchemon.experience ?? 0;
    const xpNeeded = this.selectedChuchemon.experience_for_next_level ?? 0;

    if ((this.selectedChuchemon.xuxes_exp ?? 0) < cost) {
      this.actionMessage = `Necesitas ${cost} Xux Exp (te faltan ${Math.max(0, xpNeeded - currentXp)} XP). Tienes ${this.selectedChuchemon.xuxes_exp ?? 0}.`;
      return;
    }

    const name = this.selectedChuchemon.name;
    const nextSize = this.getNextSizeLabel();

    this.levelingService.evolveChuchemon(this.selectedChuchemon.id).subscribe({
      next: (res) => {
        const xp = res.xp_gained ?? 0;
        this.actionMessage = `${res.message ?? '¡Xuxemon evolucionado!'} · +${xp} XP`;
        this.triggerEvoOverlay(name, nextSize);
        this.chuchemonService.notifyChuchemonStateChanged();
        if (xp > 0) {
          this.auth.refreshUser().subscribe();
        }
      },
      error: (err) => {
        this.actionMessage = err.error?.message ?? 'Error al evolucionar';
      }
    });
  }

  private triggerEvoOverlay(name: string, size: string): void {
    this.evoName = name;
    this.evoSize = size;
    this.showEvoOverlay = true;
    if (this.evoTimer) clearTimeout(this.evoTimer);
    this.evoTimer = setTimeout(() => {
      this.showEvoOverlay = false;
      this.evoTimer = null;
    }, 3500);
  }

  getEvolveCost(): number {
    if (!this.selectedChuchemon) return 0;
    
    const currentXp = this.selectedChuchemon.experience ?? 0;
    const xpNeeded = this.selectedChuchemon.experience_for_next_level ?? 0;
    
    if (xpNeeded <= 0) return 0;
    
    // Calcular cuántos XP faltan para evolucionar
    const xpToGo = Math.max(0, xpNeeded - currentXp);
    
    // Cada caramelo da 50 XP
    const xpPerCandy = 50;
    const candiesNeeded = Math.ceil(xpToGo / xpPerCandy);
    
    // Agregar costo extra por Bajón de azúcar
    const extraCost = this.selectedChuchemon?.evolve_cost_extra ?? 0;
    
    return candiesNeeded + extraCost;
  }

  getTotalEvolveCost(): number {
    const mida = this.selectedChuchemon?.current_mida;
    let base = 0;
    if (mida === 'Petit') base = this.evolveCostConfig.xux_petit_mitja;
    else if (mida === 'Mitjà') base = this.evolveCostConfig.xux_mitja_gran;
    return base + (this.selectedChuchemon?.evolve_cost_extra ?? 0);
  }

  getXpProgress(): number {
    if (!this.selectedChuchemon) return 0;
    const current = this.selectedChuchemon.experience ?? 0;
    const needed = this.selectedChuchemon.experience_for_next_level ?? 1;
    return needed > 0 ? Math.round((current / needed) * 100) : 0;
  }

  getXpColor(): string {
    const p = this.getXpProgress();
    if (p >= 100) return '#4caf50';
    if (p >= 66) return '#8bc34a';
    if (p >= 33) return '#ff9800';
    return '#f44336';
  }

  getNextSizeLabel(): string {
    const mida = this.selectedChuchemon?.current_mida;
    if (mida === 'Petit') return 'Mitjà';
    if (mida === 'Mitjà') return 'Gran';
    return 'Màxim';
  }

  getNextMida(): string {
    const mida = this.selectedChuchemon?.current_mida;
    if (mida === 'Petit') return 'Mitjà';
    if (mida === 'Mitjà') return 'Gran';
    return 'Gran';
  }

  healWithXux(): void {
    if (!this.selectedChuchemon || this.healQty < 1) return;
    if ((this.selectedChuchemon.xuxes_maduixa ?? 0) < this.healQty) {
      this.actionMessage = 'No tienes suficientes Xux de Maduixa para curar.';
      return;
    }

    this.openConfirmDialog(
      'Confirmar curación',
      `Vas a gastar hasta ${this.healQty} Xux de Maduixa para curar a ${this.selectedChuchemon.name} y recuperar hasta ${this.healQty * 20} PS.`,
      () => this.executeHealWithXux()
    );
  }

  private executeHealWithXux(): void {
    this.levelingService.healChuchemon(this.selectedChuchemon.id, this.healQty).subscribe({
      next: (res: any) => {
        const xpPart = (res.xp_gained ?? 0) > 0 ? ` · +${res.xp_gained} XP` : '';
        this.actionMessage = `❤️ Curado +${res.healed} PS (${res.current_hp}/${res.max_hp})${xpPart}`;

        // Actualización inmediata sin esperar el refresco async
        if (res.current_hp !== undefined) {
          const patch = {
            current_hp:    res.current_hp,
            max_hp:        res.max_hp        ?? this.selectedChuchemon.max_hp,
            xuxes_maduixa: res.xuxes_left    ?? this.selectedChuchemon.xuxes_maduixa,
          };
          this.selectedChuchemon = { ...this.selectedChuchemon, ...patch };
          this.chuchemons = this.chuchemons.map(c =>
            c.id === this.selectedChuchemon.id ? { ...c, ...patch } : c
          );
        }

        this.chuchemonService.notifyChuchemonStateChanged();
        this.auth.refreshUser().subscribe();
      },
      error: (err) => {
        this.actionMessage = err.error?.message ?? 'No se puede curar ahora';
      }
    });
  }

  getSizeLabel(size?: string): string {
    switch (size) {
      case 'Petit': return 'Petit';
      case 'Mitjà': return 'Mitjà';
      case 'Gran': return 'Gran';
      default: return size ?? 'Petit';
    }
  }

  statsForSize(size: string): { hp: number; atk: number; def: number; speed: number } {
    const c = this.selectedChuchemon;
    if (!c) return { hp: 0, atk: 0, def: 0, speed: 0 };
    const baseAtk = c.attack ?? 50;
    const baseDef = c.defense ?? 50;
    const baseSpeed = c.speed ?? 50;
    const level = c.level ?? 1;
    const attackBoost = 1 + ((c.attack_boost ?? 0) / 100);
    const defenseBoost = 1 + ((c.defense_boost ?? 0) / 100);
    let mult = 1;
    if (size === 'Mitjà') mult = 1.02;
    if (size === 'Gran') mult = 1.071;
    let hpBonus = 0;
    if (size === 'Mitjà') hpBonus = 25;
    if (size === 'Gran') hpBonus = 50;

    return {
      hp: Math.round(50 + baseDef + (level * 5) + hpBonus),
      atk: Math.round((baseAtk * mult) * attackBoost),
      def: Math.round((baseDef * mult) * defenseBoost),
      speed: Math.round(baseSpeed * mult),
    };
  }

  get hpPercent(): number {
    if (!this.selectedChuchemon) return 100;
    const c = this.selectedChuchemon;
    return c.max_hp > 0 ? Math.round((c.current_hp / c.max_hp) * 100) : 100;
  }

  get hpColor(): string {
    const p = this.hpPercent;
    if (p > 60) return '#4caf50';
    if (p > 25) return '#ff9800';
    return '#f44336';
  }

  get healPreview(): number {
    return this.healQty * 20;
  }

  get selectedInfections(): any[] {
    return this.selectedChuchemon?.active_infections ?? [];
  }

  get hasSelectedInfections(): boolean {
    return this.selectedInfections.length > 0;
  }

  trackInfection(_: number, infection: any): string {
    return `${infection.id}-${infection.name}`;
  }

  onConfirmAction(): void {
    const action = this.confirmAction;
    this.closeConfirmDialog();
    action?.();
  }

  onCancelAction(): void {
    this.closeConfirmDialog();
  }

  private openConfirmDialog(title: string, message: string, action: () => void): void {
    this.confirmTitle = title;
    this.confirmMessage = message;
    this.confirmAction = action;
    this.showActionConfirm = true;
  }

  private closeConfirmDialog(): void {
    this.showActionConfirm = false;
    this.confirmTitle = '';
    this.confirmMessage = '';
    this.confirmAction = null;
  }
}
