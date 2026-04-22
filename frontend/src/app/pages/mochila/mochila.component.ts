import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonService } from '../../services/chuchemon.service';
import { MochilaService, MochilaXuxItem } from '../../services/mochila.service';
import { ItemService, MochilaItem } from '../../services/item.service';
import { Chuchemon } from '../../models/chuchemon.model';
import { SidebarNavComponent } from '../../components/sidebar-nav/sidebar-nav.component';

// â”€â”€ Models â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

// â”€â”€ Component â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
@Component({
  selector: 'app-mochila',
  standalone: true,
  imports: [CommonModule, FormsModule, SidebarNavComponent],
  templateUrl: './mochila.component.html',
  styleUrls: ['./mochila.component.css']
})
export class MochilaComponent implements OnInit {
  user: any = null;
  loading = true;

  activeTab: 'items' | 'objetos' | 'vacunas' | 'chuchemons' = 'chuchemons';

  readonly MAX_SPACES = 20;
  readonly MAX_STACK = 5;

  // â”€â”€ Items from backend â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  items: InventoryItem[] = [];
  itemsLoading = false;
  /** Per-item quantity input map { itemId â†’ qty } */
  addItemQtyMap: { [id: number]: number } = {};
  /** Per-item feedback { itemId â†’ { type, msg } } */
  itemFeedbackMap: { [id: number]: { type: 'success' | 'warn' | 'error'; msg: string } } = {};
  addingItemMap: { [id: number]: boolean } = {};

  // â”€â”€ Xuxes from backend â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  mochilaXuxes: MochilaXuxItem[] = [];
  backendUsedSpaces = 0;
  backendFreeSpaces = 20;
  mochilaLoading = false;

  // â”€â”€ Vacunes (carregades del backend) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  vacunaItems: VacunaItem[] = [];

  // â”€â”€ Team (for apply popup) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  teamChuchemons: any[] = [];
  teamLoading = false;
  activeInfections: any[] = [];

  // â”€â”€ Chuchemons â€“ for the "Afegir Xuxes" panel â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  chuchemons: Chuchemon[] = [];
  chucemonsLoading = false;
  /** Per-chuchemon quantity input map { chuchemonId â†’ qty } */
  addQtyMap: { [id: number]: number } = {};
  /** Per-chuchemon feedback { chuchemonId â†’ { type, msg } } */
  feedbackMap: { [id: number]: { type: 'success' | 'warn' | 'error'; msg: string } } = {};
  addingMap: { [id: number]: boolean } = {};

  constructor(
    private auth: AuthService,
    private chuchemonService: ChuchemonService,
    private mochilaService: MochilaService,
    private itemService: ItemService,
    private http: HttpClient,
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
    this.loadTeam();
    this.loadInfections();
  }

  private get authHeaders(): HttpHeaders {
    return new HttpHeaders({ Authorization: 'Bearer ' + localStorage.getItem('token') });
  }

  private loadTeam() {
    this.teamLoading = true;
    this.http.get<any>('http://localhost:8000/api/user/team', { headers: this.authHeaders }).subscribe({
      next: (res) => { this.teamChuchemons = res.team ?? []; this.teamLoading = false; },
      error: () => { this.teamLoading = false; }
    });
  }

  private loadInfections() {
    this.http.get<any[]>('http://localhost:8000/api/infections', { headers: this.authHeaders }).subscribe({
      next: (data) => { this.activeInfections = data ?? []; },
      error: () => {}
    });
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
        this.mochilaXuxes   = res.items.filter((i: any) => !i.vaccine_id);
        this.backendUsedSpaces = res.used_spaces;
        this.backendFreeSpaces = res.free_spaces;

        // Cargar vacunas de la mochila
        const vaccineRows = res.items.filter((i: any) => i.vaccine_id && i.vaccine);
        this.vacunaItems = vaccineRows.map((row: any) => ({
          id: row.vaccine_id,
          type: 'no_apilable' as const,
          name: row.vaccine.name,
          description: row.vaccine.description ?? '',
          imageUrl: '',
          tag: 'Vacuna',
          quantity: row.quantity,
        }));

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

  // â”€â”€ Admin: add Xuxes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
        const msg = err?.error?.message ?? 'Error al aÃ±adir Xuxes.';
        this.feedbackMap[chuchemon.id] = { type: 'error', msg };
        this.addingMap[chuchemon.id] = false;
      }
    });
  }

  // â”€â”€ Space calculations (backend data) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  get usedSpaces(): number {
    return this.backendUsedSpaces;
  }

  get freeSpaces(): number {
    return this.backendFreeSpaces;
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
        const label = xuxItem.item?.name ?? 'Xux';
        slots.push({ index: slotIndex++, kind: 'xux', label, xuxItem, slotQty: qty });
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

  // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

  // â”€â”€ Add items â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
        const msg = err?.error?.message ?? 'Error al aÃ±adir objetos.';
        this.itemFeedbackMap[item.id] = { type: 'error', msg };
        this.addingItemMap[item.id] = false;
      }
    });
  }

  // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

  // â”€â”€ Popup â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  showPopup = false;
  popupStep: 'info' | 'select-team' = 'info';
  selectedTeamMember: any = null;
  applyFeedback: { type: 'success' | 'error'; msg: string } | null = null;
  applying = false;
  private currentSlot: InventorySlot | null = null;
  popupItem: {
    name: string;
    description: string;
    kind: 'xux' | 'vacuna';
    quantity: number;
    imageUrl?: string;
    imageEmoji?: string;
    diseases?: string[];
    applyLabel: string;
    vaccineId?: number;
  } | null = null;

  private readonly XUX_DESCRIPTIONS: { [name: string]: { description: string; emoji: string; applyLabel: string } } = {
    'Xux de Maduixa': { description: 'Recupera 20 puntos de salud (PS) por unidad.', emoji: 'ðŸ“', applyLabel: 'Curar' },
    'Xux de Llimona': { description: 'Aumenta el ataque temporalmente (+10% por unidad, mÃ¡x 50%).', emoji: 'ðŸ‹', applyLabel: 'Potenciar Ataque' },
    'Xux de Cola':    { description: 'Aumenta la defensa temporalmente (+10% por unidad, mÃ¡x 50%).', emoji: 'ðŸ¥¤', applyLabel: 'Potenciar Defensa' },
    'Xux Exp':        { description: 'Aporta 50 XP al Xuxemon y se guarda al usarlo.', emoji: 'â­', applyLabel: 'Dar XP' },
  };

  private readonly VACUNA_META: { [name: string]: { description: string; diseases: string[]; emoji: string } } = {
    'Xocolatina':     { description: 'Al usarla en un Xuxemon elimina "BajÃ³n de azÃºcar".',       diseases: ['BajÃ³n de azÃºcar'], emoji: 'ðŸ«' },
    'Xal de fruits':  { description: 'Al usarla en un Xuxemon elimina "AtracÃ³n".',               diseases: ['AtracÃ³n'],          emoji: 'ðŸ¬' },
    'Insulina':       { description: 'Cura todas las enfermedades del Xuxemon.',                  diseases: ['Todas las enfermedades'], emoji: 'ðŸ’‰' },
    'Fruita fresca':  { description: 'Al usarla en un Xuxemon elimina "Sobredosis de sucre".',   diseases: ['Sobredosis de sucre'], emoji: 'ðŸŽ' },
  };

  openPopup(slot: InventorySlot) {
    this.currentSlot = slot;
    this.popupStep = 'info';
    this.selectedTeamMember = null;
    this.applyFeedback = null;
    this.applying = false;

    if (slot.kind === 'xux' && slot.xuxItem) {
      const itemName = slot.xuxItem.item?.name ?? 'Xux';
      const meta = this.XUX_DESCRIPTIONS[itemName];
      this.popupItem = {
        name: itemName,
        description: meta?.description ?? '',
        kind: 'xux',
        quantity: slot.slotQty ?? 0,
        imageUrl: undefined,
        imageEmoji: meta?.emoji ?? 'ðŸ¬',
        applyLabel: meta?.applyLabel ?? 'Aplicar',
      };
    } else if (slot.kind === 'vacuna' && slot.vacunaItem) {
      const meta = this.VACUNA_META[slot.vacunaItem.name];
      this.popupItem = {
        name: slot.vacunaItem.name,
        description: meta?.description ?? slot.vacunaItem.description,
        kind: 'vacuna',
        quantity: slot.vacunaItem.quantity,
        imageEmoji: meta?.emoji ?? 'ðŸ’‰',
        diseases: meta?.diseases,
        applyLabel: 'Aplicar Vacuna',
        vaccineId: slot.vacunaItem.id,
      };
    }
    this.showPopup = true;
  }

  closePopup() {
    this.showPopup = false;
    this.popupItem = null;
    this.popupStep = 'info';
    this.selectedTeamMember = null;
    this.applyFeedback = null;
    this.applying = false;
    this.currentSlot = null;
  }

  goToTeamSelect() {
    this.popupStep = 'select-team';
    this.selectedTeamMember = null;
    this.applyFeedback = null;
  }

  selectTeamMember(member: any) {
    this.selectedTeamMember = member;
    this.applyFeedback = null;
  }

  infectionsFor(chuchemonId: number): any[] {
    return this.activeInfections.filter(i => i.chuchemon_id === chuchemonId);
  }

  applyItem() {
    if (!this.selectedTeamMember || !this.popupItem) return;
    const memberId = this.selectedTeamMember.id;
    this.applying = true;
    this.applyFeedback = null;

    if (this.popupItem.kind === 'xux') {
      const itemName = this.popupItem.name;
      let endpoint = '';
      let body: any = {};

      if (itemName === 'Xux de Maduixa') {
        endpoint = `http://localhost:8000/api/user/chuchemons/${memberId}/heal`;
        body = { quantity: 1 };
      } else if (itemName === 'Xux de Llimona') {
        endpoint = `http://localhost:8000/api/user/chuchemons/${memberId}/boost-attack`;
        body = { quantity: 1 };
      } else if (itemName === 'Xux de Cola') {
        endpoint = `http://localhost:8000/api/user/chuchemons/${memberId}/boost-defense`;
        body = { quantity: 1 };
      } else if (itemName === 'Xux Exp') {
        endpoint = `http://localhost:8000/api/user/chuchemons/${memberId}/use-xux`;
        body = { quantity: 1 };
      } else {
        this.applyFeedback = { type: 'error', msg: 'Tipo de xux desconocido.' };
        this.applying = false;
        return;
      }

      this.http.post<any>(endpoint, body, { headers: this.authHeaders }).subscribe({
        next: (res) => {
          const successMsg = itemName === 'Xux Exp'
            ? (res.message ?? `+${res.xp_gained ?? 50} XP aÃ±adidos y guardados.`)
            : (res.message ?? 'Â¡Aplicado!');
          this.applyFeedback = { type: 'success', msg: successMsg };
          this.applying = false;
          this.loadMochila();
          this.loadTeam();
        },
        error: (err) => {
          this.applyFeedback = { type: 'error', msg: err?.error?.message ?? 'Error al aplicar.' };
          this.applying = false;
        }
      });
    } else if (this.popupItem.kind === 'vacuna') {
      const infections = this.infectionsFor(memberId);
      if (infections.length === 0) {
        this.applyFeedback = { type: 'error', msg: 'Este Xuxemon no tiene ninguna infecciÃ³n activa.' };
        this.applying = false;
        return;
      }
      const infectionId = infections[0].id;
      const vaccineId = this.popupItem.vaccineId;
      this.http.post<any>(
        `http://localhost:8000/api/infections/cure/${infectionId}/${vaccineId}`,
        {},
        { headers: this.authHeaders }
      ).subscribe({
        next: (res) => {
          this.applyFeedback = { type: 'success', msg: res.message ?? 'Â¡InfecciÃ³n curada!' };
          this.applying = false;
          this.loadMochila();
          this.loadInfections();
          this.loadTeam();
        },
        error: (err) => {
          this.applyFeedback = { type: 'error', msg: err?.error?.message ?? 'Error al curar.' };
          this.applying = false;
        }
      });
    }
  }

  logout() {
    this.auth.logout();
  }
}

