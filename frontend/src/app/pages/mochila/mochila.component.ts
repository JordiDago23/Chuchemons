import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonService } from '../../services/chuchemon.service';
import { MochilaService, MochilaXuxItem } from '../../services/mochila.service';
import { ItemService, MochilaItem } from '../../services/item.service';
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
  type: 'apilable';
  quantity: number;
}

export interface VacunaItem extends ItemBase {
  type: 'no_apilable';
  quantity: number;
}

export type InventoryItem = XuxItem | VacunaItem;

interface InventorySlot {
  index: number;
  kind: 'xux' | 'vacuna' | 'empty';
  label: string;
  xuxItem?: MochilaXuxItem;
  vacunaItem?: VacunaItem;
  slotQty?: number;
}

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

  activeTab: 'items' | 'objetos' | 'vacunas' | 'chuchemons' = 'chuchemons';

  readonly MAX_SPACES = 20;
  readonly MAX_STACK = 5;

  // ── Items from backend ────────────────────────────────────────────────────
  items: InventoryItem[] = [];
  itemsLoading = false;
  /** Per-item quantity input map { itemId → qty } */
  addItemQtyMap: { [id: number]: number } = {};
  /** Per-item feedback { itemId → { type, msg } } */
  itemFeedbackMap: { [id: number]: { type: 'success' | 'warn' | 'error'; msg: string } } = {};
  addingItemMap: { [id: number]: boolean } = {};

  // ── Xuxes from backend ────────────────────────────────────────────────────
  mochilaXuxes: MochilaXuxItem[] = [];
  backendUsedSpaces = 0;
  backendFreeSpaces = 20;
  mochilaLoading = false;

  // ── Vacunes (hardcoded per ara) ────────────────────────────────────────────
  vacunaItems: VacunaItem[] = [
    { id: 4, type: 'no_apilable', name: 'Xocolatina',   description: 'Al usarla en un Xuxemon elimina "Bajón de azúcar".', imageUrl: '', tag: 'Estado',   quantity: 3 },
    { id: 5, type: 'no_apilable', name: 'Xal de fruites', description: 'Al usarla en un Xuxemon elimina "Atracón".',          imageUrl: '', tag: 'Estado',   quantity: 2 },
    { id: 6, type: 'no_apilable', name: 'Inxulina',      description: 'Cura todas las enfermedades del Xuxemon.',              imageUrl: '', tag: 'Cura',    quantity: 1 },
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
    private itemService: ItemService,
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
    this.loadItems();
    this.loadMochila();
    this.loadChuchemons();
  }

  private loadItems() {
    this.itemsLoading = true;
    this.itemService.getItems().subscribe({
      next: (items) => {
        // Mapear items del servicio a InventoryItem
        this.items = items.map(item => ({
          ...item,
          type: (item as any).type || 'apilable',
          quantity: (item as any).quantity || 1,
          description: item.description || '',
          imageUrl: (item as any).imageUrl || '',
          tag: (item as any).tag || ''
        } as InventoryItem));
        items.forEach(item => this.addItemQtyMap[item.id] = 1);
        this.itemsLoading = false;
      },
      error: () => { this.itemsLoading = false; }
    });
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
        const msg = err?.error?.message ?? 'Error al añadir Xuxes.';
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

  get inventorySlots(): InventorySlot[] {
    const slots: InventorySlot[] = [];
    let slotIndex = 1;

    // Xux slots: each item can span multiple stacked slots
    for (const xuxItem of this.mochilaXuxes) {
      for (const qty of this.xuxSlotBreakdownForItem(xuxItem)) {
        if (slotIndex > this.MAX_SPACES) break;
        slots.push({ index: slotIndex++, kind: 'xux', label: xuxItem.chuchemon.name, xuxItem, slotQty: qty });
      }
    }

    // Vacuna slots: each unit occupies 1 slot
    for (const vacunaItem of this.vacunaItems) {
      for (let u = 0; u < vacunaItem.quantity; u++) {
        if (slotIndex > this.MAX_SPACES) break;
        slots.push({ index: slotIndex++, kind: 'vacuna', label: vacunaItem.name, vacunaItem, slotQty: 1 });
      }
    }

    // Empty slots
    while (slotIndex <= this.MAX_SPACES) {
      slots.push({ index: slotIndex++, kind: 'empty', label: 'Libre' });
    }

    return slots;
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  get spriteUrl(): string {
    return 'assets/pokemon-sprites/';
  }

  getElementLabel(element?: string): string {
    switch (element) {
      case 'Aigua': return 'Agua';
      case 'Terra': return 'Tierra';
      case 'Aire': return 'Aire';
      default: return element ?? '';
    }
  }

  // ── Add items ──────────────────────────────────────────────────────────────
  addItems(item: InventoryItem) {
    const qty = this.addItemQtyMap[item.id] ?? 1;
    if (qty < 1) return;

    this.addingItemMap[item.id] = true;
    delete this.itemFeedbackMap[item.id];

    this.itemService.addItem(item.id, qty).subscribe({
      next: (res) => {
        this.backendUsedSpaces = res.used_spaces;
        this.backendFreeSpaces = res.free_spaces;
        this.itemFeedbackMap[item.id] = {
          type: res.added < qty ? 'warn' : 'success',
          msg: res.message,
        };
        this.addingItemMap[item.id] = false;
      },
      error: (err) => {
        const msg = err?.error?.message ?? 'Error al añadir objetos.';
        this.itemFeedbackMap[item.id] = { type: 'error', msg };
        this.addingItemMap[item.id] = false;
      }
    });
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  getMochilaItem(chuchemonId: number): MochilaXuxItem | undefined {
    return this.mochilaXuxes.find(i => i.chuchemon_id === chuchemonId);
  }

  range(n: number): number[] {
    return Array.from({ length: n }, (_, i) => i);
  }

  trackBySlot(_index: number, slot: InventorySlot): number {
    return slot.index;
  }

  setTab(tab: 'items' | 'objetos' | 'vacunas' | 'chuchemons') {
    this.activeTab = tab;
  }

  inventoryFilter: 'all' | 'xux' | 'vacuna' = 'all';

  setInventoryFilter(f: 'all' | 'xux' | 'vacuna') {
    this.inventoryFilter = f;
  }

  get filteredInventorySlots(): InventorySlot[] {
    if (this.inventoryFilter === 'all') return this.inventorySlots;
    return this.inventorySlots.filter(s => s.kind === this.inventoryFilter || s.kind === 'empty');
  }

  logout() {
    this.auth.logout();
  }
}
