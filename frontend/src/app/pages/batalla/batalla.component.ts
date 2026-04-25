import { Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import {
  BattleDetailsResponse,
  BattleOverviewResponse,
  BattleRequestSummary,
  BattleRosterEntry,
  BattleService,
  BattleSummary,
  BattleUser,
} from '../../core/services/battle.service';
import { AuthService } from '../../core/services/auth.service';
import { MainLayoutComponent } from '../../components/main-layout/main-layout.component';

@Component({
  selector: 'app-batalla',
  standalone: true,
  imports: [CommonModule, MainLayoutComponent],
  templateUrl: './batalla.component.html',
  styleUrls: ['./batalla.component.css'],
})
export class BatallaComponent implements OnInit, OnDestroy {
  user: any = null;

  onlineFriends: BattleUser[] = [];
  pendingReceived: BattleRequestSummary[] = [];
  pendingSent: BattleRequestSummary[] = [];
  activeBattles: BattleSummary[] = [];
  recentBattles: BattleSummary[] = [];

  selectedBattle: BattleSummary | null = null;
  myRoster: BattleRosterEntry[] = [];
  opponentRoster: BattleRosterEntry[] = [];

  stats = {
    victories: 0,
    defeats: 0,
    total: 0,
    win_rate: 0,
    streak: 0,
  };

  loading = true;
  battleLoading = false;
  actionLoading = '';
  success = '';
  error = '';
  currentBattleId: number | null = null;

  private readonly destroy$ = new Subject<void>();

  constructor(
    private auth: AuthService,
    private battleService: BattleService,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit(): void {
    const cachedUser = this.auth.currentUser;
    if (cachedUser) {
      this.user = cachedUser;
    } else {
      this.auth.me().subscribe({
        next: (user) => {
          this.user = user;
        },
      });
    }

    this.loadOverview();

    this.route.paramMap.pipe(takeUntil(this.destroy$)).subscribe((params) => {
      const battleId = Number(params.get('battleId') || 0);

      if (battleId > 0) {
        this.currentBattleId = battleId;
        this.fetchBattle(battleId);
        return;
      }

      this.currentBattleId = null;
      this.selectedBattle = null;
      this.myRoster = [];
      this.opponentRoster = [];
      this.loadOverview(true);
    });

    this.route.queryParamMap.pipe(takeUntil(this.destroy$)).subscribe((params) => {
      if (this.isCombatPage) {
        return;
      }

      const friendId = Number(params.get('friendId') || 0);
      if (friendId > 0) {
        this.quickChallenge(friendId);
      }
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  loadOverview(silent = false): void {
    if (!silent) {
      this.loading = true;
    }

    this.battleService.getOverview().subscribe({
      next: (response: BattleOverviewResponse) => {
        this.onlineFriends = response.online_friends ?? [];
        this.pendingReceived = response.pending_received ?? [];
        this.pendingSent = response.pending_sent ?? [];
        this.activeBattles = response.active_battles ?? [];
        this.recentBattles = response.recent_battles ?? [];
        this.stats = response.stats ?? this.stats;
        this.loading = false;
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo cargar la Arena de Batalla.';
        this.loading = false;
      },
    });
  }

  quickChallenge(friendId: number): void {
    if (!friendId || this.actionLoading === `challenge-${friendId}`) {
      return;
    }

    this.sendChallenge(friendId, true);
  }

  sendChallenge(friendId: number, silent = false): void {
    this.actionLoading = `challenge-${friendId}`;
    if (!silent) {
      this.success = '';
      this.error = '';
    }

    this.battleService.sendRequest(friendId).subscribe({
      next: (response) => {
        this.success = response.message;
        this.actionLoading = '';
        this.loadOverview(true);
        this.clearQueryParams();
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo enviar el desafío.';
        this.actionLoading = '';
      },
    });
  }

  acceptRequest(requestId: number): void {
    this.actionLoading = `accept-${requestId}`;
    this.success = '';
    this.error = '';

    this.battleService.acceptRequest(requestId).subscribe({
      next: (response) => {
        this.success = response.message;
        this.actionLoading = '';
        this.loadOverview(true);
        this.openBattle(response.battle_id);
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo aceptar el desafío.';
        this.actionLoading = '';
      },
    });
  }

  deleteRequest(requestId: number): void {
    this.actionLoading = `delete-${requestId}`;
    this.success = '';
    this.error = '';

    this.battleService.deleteRequest(requestId).subscribe({
      next: (response) => {
        this.success = response.message;
        this.actionLoading = '';
        this.loadOverview(true);
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo eliminar la solicitud.';
        this.actionLoading = '';
      },
    });
  }

  openBattle(battleId: number): void {
    this.router.navigate(['/batalla', battleId]);
  }

  private fetchBattle(battleId: number): void {
    this.battleLoading = true;
    this.success = '';
    this.error = '';

    this.battleService.getBattle(battleId).subscribe({
      next: (response: BattleDetailsResponse) => {
        this.selectedBattle = response.battle;
        this.myRoster = response.my_roster ?? [];
        this.opponentRoster = response.opponent_roster ?? [];
        this.battleLoading = false;
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo abrir la batalla.';
        this.battleLoading = false;
      },
    });
  }

  selectForBattle(chuchemonId: number): void {
    if (!this.selectedBattle) {
      return;
    }

    this.actionLoading = `select-${chuchemonId}`;
    this.success = '';
    this.error = '';

    this.battleService.selectChuchemon(this.selectedBattle.id, chuchemonId).subscribe({
      next: (response) => {
        this.selectedBattle = response.battle;
        this.success = response.message;
        this.actionLoading = '';
        this.loadOverview(true);
      },
      error: (err) => {
        this.error = err.error?.message ?? 'No se pudo enviar la selección.';
        this.actionLoading = '';
      },
    });
  }

  closeBattlePanel(): void {
    this.router.navigate(['/batalla']);
  }

  get isCombatPage(): boolean {
    return this.currentBattleId !== null;
  }

  isLoadingAction(prefix: string, id: number): boolean {
    return this.actionLoading === `${prefix}-${id}`;
  }

  get canSelect(): boolean {
    return !!this.selectedBattle && this.selectedBattle.status === 'pending_selection';
  }

  get hasResult(): boolean {
    return !!this.selectedBattle && this.selectedBattle.status === 'completed';
  }

  get hasBothSelections(): boolean {
    return !!this.selectedBattle?.my_selection && !!this.selectedBattle?.opponent_selection;
  }

  get battlePhaseLabel(): string {
    if (!this.selectedBattle) {
      return '';
    }

    return this.selectedBattle.status === 'completed' ? 'Resultado final' : 'Esperando selección';
  }

  get myFighter(): BattleRosterEntry | null {
    const id = this.selectedBattle?.my_selection;
    if (!id) {
      return null;
    }

    return this.myRoster.find((entry) => entry.id === id) ?? null;
  }

  get opponentFighter(): BattleRosterEntry | null {
    const id = this.selectedBattle?.opponent_selection;
    if (!id) {
      return null;
    }

    return this.opponentRoster.find((entry) => entry.id === id) ?? null;
  }

  get battleLogLines(): string[] {
    const battle = this.selectedBattle;
    if (!battle) {
      return [];
    }

    const lines: string[] = ['La batalla ha comenzado.'];

    if (!battle.my_selection) {
      lines.push('Selecciona tu Xuxemon para empezar el combate.');
      return lines;
    }

    if (!battle.opponent_selection) {
      lines.push('Tu selección está lista. Esperando al oponente.');
      return lines;
    }

    const myName = this.myFighter?.name ?? 'Tu Xuxemon';
    const oppName = this.opponentFighter?.name ?? 'Xuxemon rival';
    lines.push(`${myName} vs ${oppName}.`);

    if (battle.status === 'completed') {
      lines.push(this.resultText);
      lines.push(
        `Tu cálculo: ${this.myRoll() ?? '-'} + ${this.formatSigned(this.myTypeMod())} + ${this.formatSigned(this.mySizeMod())} = ${this.myTotal() ?? '-'}`
      );
      lines.push(
        `Rival: ${this.opponentRoll() ?? '-'} + ${this.formatSigned(this.opponentTypeMod())} + ${this.formatSigned(this.opponentSizeMod())} = ${this.opponentTotal() ?? '-'}`
      );
    }

    return lines;
  }

  get didIWin(): boolean {
    return !!this.selectedBattle && !!this.user && this.selectedBattle.winner_id === this.user.id;
  }

  get resultText(): string {
    if (!this.selectedBattle || !this.hasResult) {
      return '';
    }

    return this.didIWin
      ? 'Has ganado la batalla y robas el Xuxemon seleccionado del rival.'
      : 'Has perdido la batalla y el rival te roba el Xuxemon seleccionado.';
  }

  battleOutcomeLabel(battle: BattleSummary): string {
    if (!this.user || battle.status !== 'completed') {
      return 'Pendiente';
    }

    if (battle.winner_id === this.user.id) {
      return 'Victoria';
    }

    if (battle.loser_id === this.user.id) {
      return 'Derrota';
    }

    return 'Completada';
  }

  myRoll(): number | null {
    return this.battleMetric('rolls', 'me');
  }

  opponentRoll(): number | null {
    return this.battleMetric('rolls', 'opponent');
  }

  myTypeMod(): number | null {
    return this.battleMetric('type_modifiers', 'me');
  }

  opponentTypeMod(): number | null {
    return this.battleMetric('type_modifiers', 'opponent');
  }

  mySizeMod(): number | null {
    return this.battleMetric('size_modifiers', 'me');
  }

  opponentSizeMod(): number | null {
    return this.battleMetric('size_modifiers', 'opponent');
  }

  myTotal(): number | null {
    return this.battleMetric('final_scores', 'me');
  }

  opponentTotal(): number | null {
    return this.battleMetric('final_scores', 'opponent');
  }

  formatSigned(value: number | null): string {
    if (value === null || Number.isNaN(value)) {
      return '-';
    }

    return value >= 0 ? `+${value}` : `${value}`;
  }

  actionUnavailable(action: string): void {
    this.success = `La acción "${action}" está en vista previa. La resolución actual es automática al seleccionar ambos Xuxemons.`;
    this.error = '';
  }

  imageFor(entry: BattleRosterEntry | null): string {
    if (!entry?.image) {
      return 'https://placehold.co/760x340?text=Xuxemon';
    }

    return entry.image;
  }

  private battleMetric(
    metric: 'rolls' | 'type_modifiers' | 'size_modifiers' | 'final_scores',
    side: 'me' | 'opponent'
  ): number | null {
    const battle = this.selectedBattle;
    const payload = battle?.result_payload;
    const userId = this.user?.id;

    if (!battle || !payload || !userId) {
      return null;
    }

    const myRole = this.roleForUser(battle, userId);
    if (!myRole) {
      return null;
    }

    const role = side === 'me' ? myRole : myRole === 'challenger' ? 'challenged' : 'challenger';
    return payload[metric]?.[role] ?? null;
  }

  private roleForUser(battle: BattleSummary, userId: number): 'challenger' | 'challenged' | null {
    if (battle.challenger_id === userId) {
      return 'challenger';
    }

    if (battle.challenged_id === userId) {
      return 'challenged';
    }

    return null;
  }

  trackById(_: number, item: { id: number }): number {
    return item.id;
  }

  logout(): void {
    this.auth.logout();
  }

  private clearQueryParams(): void {
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: {},
      replaceUrl: true,
    });
  }
}

