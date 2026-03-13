import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonService } from '../../services/chuchemon.service';
import { MochilaService, MochilaXuxItem } from '../../services/mochila.service';
import { Chuchemon } from '../../models/chuchemon.model';

// ── Models ────────────────────────────────────────────────────────────────────
export interface ItemBase {
  id: number;
  name: string;
  description: string;
  imageUrl: string;
  tag: string;
}

export interface XuxItem extends ItemBase {
  type: 'xux';
  quantity: number;
}

export interface VacunaItem extends ItemBase {
  type: 'vacuna';
  quantity: number;
}

export type Item = XuxItem | VacunaItem;

// ── Component ─────────────────────────────────────────────────────────────────
@Component({
  selector: 'app-mochila',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './mochila.component.html',
  styleUrls: ['./mochila.component.css']
})
export class MochilaComponent implements OnInit {
  user: any = null;
  loading = true;

  activeTab: 'objetos' | 'vacunas' | 'chuchemons' = 'chuchemons';

  readonly MAX_SPACES = 20;
  readonly MAX_STACK = 5;

  // ── Xuxes from backend ────────────────────────────────────────────────────
  mochilaXuxes: MochilaXuxItem[] = [];
  backendUsedSpaces = 0;
  backendFreeSpaces = 20;
  mochilaLoading = false;

  // ── Vacunes (hardcoded per ara) ────────────────────────────────────────────
  vacunaItems: VacunaItem[] = [
    { id: 4, type: 'vacuna', name: 'Xocolatina',   description: 'En usar-ho en un Xuxemon treu "Bajón de azúcar".', imageUrl: '', tag: 'Estat',   quantity: 3 },
    { id: 5, type: 'vacuna', name: 'Xal de fruites', description: 'En usar-ho en un Xuxemon treu "Atracón".',          imageUrl: '', tag: 'Estat',   quantity: 2 },
    { id: 6, type: 'vacuna', name: 'Inxulina',      description: 'Cura totes les malalties del Xuxemon.',              imageUrl: '', tag: 'Cura',    quantity: 1 },
  ];

  // ── Chuchemons – for the "Afegir Xuxes" panel ─────────────────────────────
  chuchemons: Chuchemon[] = [];
  chucemonsLoading = false;
  /** Per-chuchemon quantity input map { chuchemonId → qty } */
  addQtyMap: { [id: number]: number } = {};
  /** Per-chuchemon feedback { chuchemonId → { type, msg } } */
  feedbackMap: { [id: number]: { type: 'success' | 'warn' | 'error'; msg: string } } = {};
  addingMap: { [id: number]: boolean } = {};

  constructor(
    private auth: AuthService,
    private chuchemonService: ChuchemonService,
    private mochilaService: MochilaService,
  ) {}

  ngOnInit() {
    const cached = this.auth.currentUser;
    if (cached) {
      this.user = cached;
      this.loading = false;
      this.afterUserLoaded();
      return;
    }
    this.auth.me().subscribe({
      next: (data) => {
        this.user = data;
        this.loading = false;
        this.afterUserLoaded();
      },
      error: () => {
        this.loading = false;
        this.auth.logout();
      }
    });
  }

  private afterUserLoaded() {
    this.loadMochila();
    this.loadChuchemons();
  }

  private loadMochila() {
    this.mochilaLoading = true;
    this.mochilaService.getMochila().subscribe({
      next: (res) => {
        this.mochilaXuxes   = res.items;
        this.backendUsedSpaces = res.used_spaces;
        this.backendFreeSpaces = res.free_spaces;
        this.mochilaLoading = false;
      },
      error: () => { this.mochilaLoading = false; }
    });
  }

  private loadChuchemons() {
    this.chucemonsLoading = true;
    this.chuchemonService.getAllChuchemons().subscribe({
      next: (list) => {
        this.chuchemons = list;
        list.forEach(c => this.addQtyMap[c.id] = 1);
        this.chucemonsLoading = false;
      },
      error: () => { this.chucemonsLoading = false; }
    });
  }

  // ── Admin: add Xuxes ──────────────────────────────────────────────────────
  addXuxes(chuchemon: Chuchemon) {
    const qty = this.addQtyMap[chuchemon.id] ?? 1;
    if (qty < 1) return;

    this.addingMap[chuchemon.id] = true;
    delete this.feedbackMap[chuchemon.id];

    this.mochilaService.addXux(chuchemon.id, qty).subscribe({
      next: (res) => {
        this.backendUsedSpaces = res.used_spaces;
        this.backendFreeSpaces = res.free_spaces;
        // Update or insert into mochilaXuxes
        const idx = this.mochilaXuxes.findIndex(i => i.chuchemon_id === chuchemon.id);
        if (idx >= 0) {
          this.mochilaXuxes[idx] = res.item;
        } else {
          this.mochilaXuxes.push(res.item);
        }
        this.feedbackMap[chuchemon.id] = {
          type: res.discarded > 0 ? 'warn' : 'success',
          msg:  res.message,
        };
        this.addingMap[chuchemon.id] = false;
      },
      error: (err) => {
        const msg = err?.error?.message ?? 'Error en afegir Xuxes.';
        this.feedbackMap[chuchemon.id] = { type: 'error', msg };
        this.addingMap[chuchemon.id] = false;
      }
    });
  }

  // ── Space calculations (backend data) ─────────────────────────────────────
  get usedSpaces(): number {
    return this.backendUsedSpaces + this.totalVacunaSlots;
  }

  get freeSpaces(): number {
    return this.MAX_SPACES - this.usedSpaces;
  }

  get spacePercent(): number {
    return Math.min(100, Math.round((this.usedSpaces / this.MAX_SPACES) * 100));
  }

  xuxSlotsForItem(item: MochilaXuxItem): number {
    return Math.ceil(item.quantity / this.MAX_STACK);
  }

  xuxSlotBreakdownForItem(item: MochilaXuxItem): number[] {
    const slots: number[] = [];
    let remaining = item.quantity;
    while (remaining > 0) {
      slots.push(Math.min(remaining, this.MAX_STACK));
      remaining -= this.MAX_STACK;
    }
    return slots;
  }

  get totalXuxSlots(): number {
    return this.mochilaXuxes.reduce((sum, i) => sum + this.xuxSlotsForItem(i), 0);
  }

  get totalVacunaSlots(): number {
    return this.vacunaItems.reduce((sum, i) => sum + i.quantity, 0);
  }

  get totalXuxQuantity(): number {
    return this.mochilaXuxes.reduce((s, i) => s + i.quantity, 0);
  }

  get xuxTypeCount(): number {
    return this.mochilaXuxes.filter(i => i.quantity > 0).length;
  }

  get totalVacunaQuantity(): number {
    return this.vacunaItems.reduce((s, i) => s + i.quantity, 0);
  }

  get vacunaTypeCount(): number {
    return this.vacunaItems.filter(i => i.quantity > 0).length;
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  getMochilaItem(chuchemonId: number): MochilaXuxItem | undefined {
    return this.mochilaXuxes.find(i => i.chuchemon_id === chuchemonId);
  }

  range(n: number): number[] {
    return Array.from({ length: n }, (_, i) => i);
  }

  setTab(tab: 'objetos' | 'vacunas' | 'chuchemons') {
    this.activeTab = tab;
  }

  logout() {
    this.auth.logout();
  }
}
