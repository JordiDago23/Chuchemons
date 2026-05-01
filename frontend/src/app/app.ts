import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Meta, Title } from '@angular/platform-browser';
import { ActivatedRoute, NavigationEnd, Router, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs/operators';
import { LoadingService } from './core/services/loading.service';
import { ConfigService } from './services/config.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class App {
  private readonly router = inject(Router);
  private readonly activatedRoute = inject(ActivatedRoute);
  private readonly titleService = inject(Title);
  private readonly meta = inject(Meta);
  private readonly destroyRef = inject(DestroyRef);
  private readonly loading = inject(LoadingService);
  private readonly configService = inject(ConfigService);

  protected readonly title = signal('frontend');
  protected readonly isGlobalLoading = signal(false);

  constructor() {
    // Iniciar polling global de configuraciones
    this.configService.startPolling();
    this.loading.isLoading$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.isGlobalLoading.set(value));

    this.router.events
      .pipe(
        filter((event): event is NavigationEnd => event instanceof NavigationEnd),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe(() => {
        let route = this.activatedRoute;

        while (route.firstChild) {
          route = route.firstChild;
        }

        const title = route.snapshot.title;
        const description = route.snapshot.data['description'];
        const keywords = route.snapshot.data['keywords'];

        if (title) {
          this.titleService.setTitle(title);
        }

        if (description) {
          this.meta.updateTag({ name: 'description', content: description });
        }

        if (keywords) {
          this.meta.updateTag({ name: 'keywords', content: keywords });
        }
      });
  }
}
