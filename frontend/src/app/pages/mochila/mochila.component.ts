import { Component, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { AuthService } from '../../core/services/auth.service';
import { ChuchemonService } from '../../services/chuchemon.service';
import { MochilaService, MochilaXuxItem } from '../../services/mochila.service';
import { ItemService, MochilaItem } from '../../services/item.service';
import { Chuchemon } from '../../models/chuchemon.model';
import { MainLayoutComponent } from '../../components/main-layout/main-layout.component';

// Models
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
  mochilaRecordId?: number; // ID del registro en mochila_xuxes (para eliminar)
}

// Component
@Component({
  selector: 'app-mochila',
  standalone: true,
  imports: [CommonModule, FormsModule, MainLayoutComponent],
  templateUrl: './mochila.component.html',
  styleUrls: ['./mochila.component.css']
})
export class MochilaComponent implements OnInit, OnDestroy {
  user: any = null;
  loading = true;

  activeTab: 'items' | 'objetos' | 'vacunas' | 'chuchemons' = 'chuchemons';

  readonly MAX_SPACES = 20;
  readonly MAX_STACK = 5;

  private destroy$ = new Subject<void>();

  // Items from backend
  items: InventoryItem[] = [];
  itemsLoading = false;
  /** Per-item quantity input map { itemId → qty } */
  addItemQtyMap: { [id: number]: number } = {};
  /** Per-item feedback { itemId → { type, msg } } */
  itemFeedbackMap: { [id: number]: { type: 'success' | 'warn' | 'error'; msg: string } } = {};
  addingItemMap: { [id: number]: boolean } = {};

  // Xuxes from backend
  mochilaXuxes: MochilaXuxItem[] = [];
  backendUsedSpaces = 0;
  backendFreeSpaces = 20;
  mochilaLoading = false;

  // Vacunes (carregades del backend)
  vacunaItems: VacunaItem[] = [];

  // Team (for apply popup)
  teamChuchemons: any[] = [];
  teamLoading = false;
  activeInfections: any[] = [];

  // Chuchemons – for the "Afegir Xuxes" panel
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
    this.loadChuchemons();
    this.loadTeam();
    this.loadInfections();
    
    // Suscribirse a actualizaciones reactivas de mochila
    this.mochilaService.mochilaData$
      .pipe(takeUntil(this.destroy$))
      .subscribe(data => {
        if (data) {
          // Guardar TODOS los items sin filtrar (incluyendo vacunas)
          this.mochilaXuxes = data.items;
          
          this.backendUsedSpaces = data.used_spaces;
          this.backendFreeSpaces = data.free_spaces;

          this.mochilaLoading = false;
        }
      });
    
    // Cargar mochila inicialmente
    this.loadMochila();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
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
    this.mochilaService.refreshMochila();
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

  // Admin: add Xuxes
  addXuxes(chuchemon: Chuchemon) {
    const qty = this.addQtyMap[chuchemon.id] ?? 1;
    if (qty < 1) return;

    this.addingMap[chuchemon.id] = true;
    delete this.feedbackMap[chuchemon.id];

    this.mochilaService.addXux(chuchemon.id, qty).subscribe({
      next: (res) => {
        // Refrescar mochila reactivamente
        this.mochilaService.refreshMochila();
        
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

  // Space calculations (backend data)
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
    const xuxItems = this.mochilaXuxes.filter(i => !i.vaccine_id);
    return xuxItems.reduce((sum, i) => sum + this.xuxSlotsForItem(i), 0);
  }

  get totalVacunaSlots(): number {
    const vacunaItems = this.mochilaXuxes.filter(i => i.vaccine_id);
    return vacunaItems.reduce((sum, i) => sum + Math.ceil(i.quantity / this.MAX_STACK), 0);
  }

  get totalXuxQuantity(): number {
    const xuxItems = this.mochilaXuxes.filter(i => !i.vaccine_id);
    return xuxItems.reduce((s, i) => s + i.quantity, 0);
  }

  get xuxTypeCount(): number {
    const xuxItems = this.mochilaXuxes.filter(i => !i.vaccine_id && i.quantity > 0);
    return xuxItems.length;
  }

  get totalVacunaQuantity(): number {
    const vacunaItems = this.mochilaXuxes.filter(i => i.vaccine_id);
    return vacunaItems.reduce((s, i) => s + i.quantity, 0);
  }

  get vacunaTypeCount(): number {
    const vacunaItems = this.mochilaXuxes.filter(i => i.vaccine_id && i.quantity > 0);
    return vacunaItems.length;
  }

  get inventorySlots(): InventorySlot[] {
    const slots: InventorySlot[] = [];
    let slotIndex = 1;

    // Xux slots: each item can span multiple stacked slots (items y chuchemons)
    const xuxRecords = this.mochilaXuxes.filter(i => !i.vaccine_id);
    
    for (const xuxItem of xuxRecords) {
      for (const qty of this.xuxSlotBreakdownForItem(xuxItem)) {
        if (slotIndex > this.MAX_SPACES) break;
        const label = xuxItem.item?.name ?? xuxItem.chuchemon?.name ?? 'Xux';
        slots.push({ 
          index: slotIndex++, 
          kind: 'xux', 
          label, 
          xuxItem, 
          slotQty: qty,
          mochilaRecordId: xuxItem.id 
        });
      }
    }

    // Vacuna slots: apilables 5 por slot (igual que xuxes)
    const vacunaRecords = this.mochilaXuxes.filter(i => i.vaccine_id && i.vaccine);

    for (const record of vacunaRecords) {
      for (const qty of this.xuxSlotBreakdownForItem(record)) {
        if (slotIndex > this.MAX_SPACES) break;
        const vacunaItem: VacunaItem = {
          id: record.vaccine!.id,
          type: 'no_apilable',
          name: record.vaccine!.name,
          description: record.vaccine!.description ?? '',
          imageUrl: '',
          tag: 'Vacuna',
          quantity: record.quantity
        };
        slots.push({
          index: slotIndex++,
          kind: 'vacuna',
          label: record.vaccine!.name,
          vacunaItem,
          slotQty: qty,
          mochilaRecordId: record.id
        });
      }
    }

    // Empty slots
    while (slotIndex <= this.MAX_SPACES) {
      slots.push({ index: slotIndex++, kind: 'empty', label: 'Libre' });
    }

    return slots;
  }

  // Helpers
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

  // Add items
  addItems(item: InventoryItem) {
    const qty = this.addItemQtyMap[item.id] ?? 1;
    if (qty < 1) return;

    this.addingItemMap[item.id] = true;
    delete this.itemFeedbackMap[item.id];

    this.itemService.addItem(item.id, qty).subscribe({
      next: (res) => {
        // Refrescar mochila reactivamente
        this.mochilaService.refreshMochila();
        
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

  // Helpers
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

  // Popup
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
    'Xux de Maduixa': { description: 'Recupera 20 puntos de salud (PS) por unidad.', emoji: '🍓', applyLabel: 'Curar' },
    'Xux de Llimona': { description: 'Aumenta el ataque temporalmente (+10% por unidad, máx 50%).', emoji: '🍋', applyLabel: 'Potenciar Ataque' },
    'Xux de Cola':    { description: 'Aumenta la defensa temporalmente (+10% por unidad, máx 50%).', emoji: '🥤', applyLabel: 'Potenciar Defensa' },
    'Xux Exp':        { description: 'Aporta 50 XP al Xuxemon y se guarda al usarlo.', emoji: '⭐', applyLabel: 'Dar XP' },
  };

  private readonly VACUNA_META: { [name: string]: { description: string; diseases: string[]; emoji: string } } = {
    'Xocolatina':     { description: 'Al usarla en un Xuxemon elimina "Bajón de azúcar".',       diseases: ['Bajón de azúcar'], emoji: '🍫' },
    'Xal de fruits':  { description: 'Al usarla en un Xuxemon elimina "Atracón".',               diseases: ['Atracón'],          emoji: '🍬' },
    'Insulina':       { description: 'Cura todas las enfermedades del Xuxemon.',                  diseases: ['Todas las enfermedades'], emoji: '💉' },
    'Fruita fresca':  { description: 'Al usarla en un Xuxemon elimina "Sobredosis de sucre".',   diseases: ['Sobredosis de sucre'], emoji: '🍎' },
  };

  getVaccineEmoji(vaccineName: string): string {
    return this.VACUNA_META[vaccineName]?.emoji ?? '💉';
  }

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
        imageEmoji: meta?.emoji ?? '🍬',
        applyLabel: meta?.applyLabel ?? 'Aplicar',
      };
    } else if (slot.kind === 'vacuna' && slot.vacunaItem) {
      const meta = this.VACUNA_META[slot.vacunaItem.name];
      this.popupItem = {
        name: slot.vacunaItem.name,
        description: meta?.description ?? slot.vacunaItem.description,
        kind: 'vacuna',
        quantity: slot.vacunaItem.quantity,
        imageEmoji: meta?.emoji ?? '💉',
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
            ? (res.message ?? `+${res.xp_gained ?? 50} XP añadidos y guardados.`)
            : (res.message ?? '¡Aplicado!');
          this.applyFeedback = { type: 'success', msg: successMsg };
          this.applying = false;
          this.loadMochila();
          this.loadTeam();
          this.chuchemonService.notifyChuchemonStateChanged();
        },
        error: (err) => {
          this.applyFeedback = { type: 'error', msg: err?.error?.message ?? 'Error al aplicar.' };
          this.applying = false;
        }
      });
    } else if (this.popupItem.kind === 'vacuna') {
      const infections = this.infectionsFor(memberId);
      if (infections.length === 0) {
        this.applyFeedback = { type: 'error', msg: 'Este Xuxemon no tiene ninguna infección activa.' };
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
          this.applyFeedback = { type: 'success', msg: res.message ?? '¡Infección curada!' };
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

  // Delete item from mochila
  deleteItem(slot: InventorySlot, event: Event) {
    event.stopPropagation(); // Evitar que se abra el popup
    
    // Usar mochilaRecordId directamente (ya guardado en el slot)
    const mochilaItemId = slot.mochilaRecordId;
    
    if (!mochilaItemId) {
      console.error('No se encontró el ID del item en la mochila');
      return;
    }
    
    // Llamar al endpoint DELETE /api/mochila/{id}
    this.http.delete<any>(`http://localhost:8000/api/mochila/${mochilaItemId}`, { headers: this.authHeaders })
      .subscribe({
        next: (res) => {
          // Actualizar espacios del backend
          this.backendFreeSpaces = res.free_spaces;
          this.backendUsedSpaces = res.used_spaces;
          
          // Refrescar mochila reactivamente
          this.mochilaService.refreshMochila();
        },
        error: (err) => {
          console.error('Error al eliminar item:', err);
          alert(err?.error?.message ?? 'Error al eliminar el item');
        }
      });
  }

  logout() {
    this.auth.logout();
  }
}

