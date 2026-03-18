import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-chuchemon-card',
  standalone: true,
  imports: [CommonModule],
  template: `
    <article
      class="cc-card card shadow-sm"
      [ngClass]="{
        'cc-locked':   locked,
        'cc-tipo-agua':   chuchemon?.element === 'Agua',
        'cc-tipo-tierra': chuchemon?.element === 'Tierra',
        'cc-tipo-aire':   chuchemon?.element === 'Aire'
      }"
      tabindex="0"
      (click)="onCardClick()"
      (keydown.enter)="onCardClick()"
      (keydown.space)="$event.preventDefault(); onCardClick()"
      [attr.aria-label]="locked
        ? 'Xuxemon desconegut, no capturat'
        : (chuchemon?.name ?? 'Xuxemon') + ', tipus ' + chuchemon?.element + ', mida ' + sizeBadge"
    >
      <!-- Badges flex: tipo · mida · quantitat -->
      <div class="cc-badges">
        <span
          class="cc-badge cc-badge-tipo"
          [ngClass]="{
            'badge-agua':   chuchemon?.element === 'Agua',
            'badge-tierra': chuchemon?.element === 'Tierra',
            'badge-aire':   chuchemon?.element === 'Aire'
          }"
        >{{ chuchemon?.element }}</span>
        <span class="cc-badge cc-badge-mida">Mida {{ sizeBadge }}</span>
        <span class="cc-badge cc-badge-qty">Qtat {{ quantityLabel }}</span>
        @if (showTeamBadge) {
          <span class="cc-badge cc-badge-team">Equip</span>
        }
      </div>

      <!-- Imatge -->
      <div class="cc-img-wrap" [class.cc-blurred]="locked">
        @if (chuchemon?.image) {
          <img
            [src]="'http://localhost:8000/chuchemons/' + chuchemon!.image"
            [alt]="locked
              ? 'Xuxemon desconegut — imatge amagada'
              : chuchemon!.name + ' — Xuxemon de tipus ' + chuchemon!.element"
            class="cc-img"
            onerror="this.src='assets/placeholder.png'"
          />
        } @else {
          <span class="cc-placeholder">🐾</span>
        }
        @if (locked) {
          <div class="cc-lock" aria-hidden="true">🔒</div>
          <span class="sr-only">Xuxemon bloquejat: no capturat</span>
        }
      </div>

      <!-- Info -->
      <div class="cc-info">
        <div class="cc-header">
          <h2 class="cc-name">{{ locked ? '?????' : (chuchemon?.name ?? '?') }}</h2>
          <span class="sr-only">Estat: {{ locked ? 'No capturat' : 'Capturat' }}. Mida: {{ sizeBadge }}.</span>
          <span class="cc-number">Nº {{ locked ? '?' : chuchemon?.id }}</span>
        </div>

        @if (!locked) {
          <div class="cc-hp-row">
            <span>HP</span><span class="cc-hp-val">100/100</span>
          </div>
          <div class="cc-hp-bar"><div class="cc-hp-fill" style="width:100%"></div></div>
        }

        @if (!locked && showCaptureBtn) {
          <button
            class="cc-btn cc-btn-capture"
            (click)="onCapture($event)"
            [attr.aria-label]="'Capturar ' + (chuchemon?.name ?? 'aquest Xuxemon')"
          >Capturar</button>
        }
        @if (!locked && showDetailsBtn) {
          <button
            class="cc-btn cc-btn-details"
            (click)="onDetails($event)"
            [attr.aria-label]="'Veure detalls de ' + (chuchemon?.name ?? 'aquest Xuxemon')"
          >Veure Detalls</button>
        }
      </div>
    </article>
  `,
  styles: [`
    .cc-card {
      border-radius: 12px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      border: 1px solid #ececec;
      background: #fff;
      transition: transform .25s ease, box-shadow .25s ease;
      cursor: pointer;
      height: 100%;
    }
    .cc-card:hover { transform: translateY(-5px) scale(1.01); box-shadow: 0 10px 24px rgba(0,0,0,.13); }

    /* tipo background tint */
    .cc-tipo-agua   { border-top: 3px solid #3b5bdb; }
    .cc-tipo-tierra { border-top: 3px solid #d4a574; }
    .cc-tipo-aire   { border-top: 3px solid #87ceeb; }

    /* badges row */
    .cc-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      align-items: center;
      padding: 8px 10px;
      border-bottom: 1px solid #f0f0f0;
      min-height: 40px;
    }
    .cc-badge {
      display: inline-flex;
      align-items: center;
      padding: 3px 10px;
      border-radius: 999px;
      font-size: .72rem;
      font-weight: 700;
    }
    .cc-badge-tipo { color: #fff; }
    .badge-agua   { background: #3b5bdb; }
    .badge-tierra { background: #c27740; color: #fff; }
    .badge-aire   { background: #87ceeb; color: #1a415a; }
    .cc-badge-mida { background: #264653; color: #fff; }
    .cc-badge-qty  { background: #f4a261; color: #2b1a00; }
    .cc-badge-team { background: #4caf50; color: #fff; }

    /* image */
    .cc-img-wrap {
      width: 100%; height: 130px;
      background: linear-gradient(135deg,#f5f7fa,#e0e7ff);
      display: flex; align-items: center; justify-content: center;
      position: relative; overflow: hidden; flex-shrink: 0;
    }
    .cc-img { width:100%; height:100%; object-fit:contain; padding:8px; transition: transform .3s ease; }
    .cc-card:hover .cc-img { transform: scale(1.1); }
    .cc-blurred { filter: blur(6px); }
    .cc-placeholder { font-size: 3rem; }
    .cc-lock {
      position: absolute; top: 50%; left: 50%;
      transform: translate(-50%,-50%);
      font-size: 2.5rem;
      filter: drop-shadow(0 2px 4px rgba(0,0,0,.3));
    }

    /* info */
    .cc-info { padding: 10px 12px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
    .cc-header { display: flex; justify-content: space-between; align-items: flex-start; }
    .cc-name { margin: 0; font-size: .95rem; font-weight: 700; color: #1a1a2e; flex: 1; }
    .cc-number { font-size: .78rem; color: #999; white-space: nowrap; }
    .cc-hp-row { display: flex; justify-content: space-between; font-size: .82rem; color: #666; }
    .cc-hp-val { font-weight: 600; color: #333; }
    .cc-hp-bar { height: 4px; background: #e0e0e0; border-radius: 3px; overflow: hidden; }
    .cc-hp-fill { height: 100%; background: linear-gradient(90deg,#e63946,#f4722b); }

    /* buttons */
    .cc-btn {
      margin-top: auto;
      padding: 7px 14px;
      border: none;
      border-radius: 6px;
      font-size: .83rem;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s ease;
    }
    .cc-btn-capture { background: linear-gradient(135deg,#4caf50,#45a049); color: #fff; }
    .cc-btn-capture:hover { transform: scale(1.03); box-shadow: 0 4px 12px rgba(76,175,80,.3); }
    .cc-btn-details { background: linear-gradient(135deg,#e63946,#f4722b); color: #fff; }
    .cc-btn-details:hover { transform: scale(1.03); box-shadow: 0 4px 12px rgba(230,57,70,.3); }

    /* locked card */
    .cc-locked { opacity: .85; }

    /* ── Accessible sr-only utility ─────────────────────────────────────── */
    .sr-only {
      position: absolute; width: 1px; height: 1px;
      padding: 0; margin: -1px; overflow: hidden;
      clip: rect(0,0,0,0); white-space: nowrap; border: 0;
    }

    /* ── Focus visual — distint de hover ────────────────────────────────── */
    .cc-card:focus-visible {
      outline: 3px solid #3b5bdb;
      outline-offset: 3px;
      box-shadow: 0 0 0 5px rgba(59,91,219,.2);
      transform: translateY(-2px); /* diferent de hover que fa -5px scale */
    }
    .cc-btn:focus-visible {
      outline: 3px solid #fff;
      outline-offset: 2px;
      box-shadow: 0 0 0 4px #3b5bdb;
    }
  `]
})
export class ChuchemonCardComponent {
  @Input() chuchemon: any = null;
  @Input() locked = false;
  @Input() showCaptureBtn = false;
  @Input() showDetailsBtn = false;
  @Input() showTeamBadge = false;

  @Output() capture  = new EventEmitter<number>();
  @Output() details  = new EventEmitter<number>();
  @Output() cardClick = new EventEmitter<any>();

  get sizeBadge(): string {
    const count = this.chuchemon?.count ?? 1;
    if (count >= 5) return 'Gran';
    if (count >= 3) return 'Mitjà';
    return 'Petit';
  }

  get quantityLabel(): string {
    if (this.locked) return 'x?';
    return `x${this.chuchemon?.count ?? 1}`;
  }

  onCapture(e: Event): void {
    e.stopPropagation();
    if (this.chuchemon?.id) this.capture.emit(this.chuchemon.id);
  }

  onDetails(e: Event): void {
    e.stopPropagation();
    if (this.chuchemon?.id) this.details.emit(this.chuchemon.id);
  }

  onCardClick(): void {
    this.cardClick.emit(this.chuchemon);
  }
}
