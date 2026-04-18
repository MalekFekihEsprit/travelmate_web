import { startStimulusApp } from '@symfony/stimulus-bundle';
import VoyageFiltersController from './controllers/voyage_filters_controller.js';

const app = startStimulusApp();
app.debug = false;
window.Stimulus = app;

// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
app.register('voyage-filters', VoyageFiltersController);
