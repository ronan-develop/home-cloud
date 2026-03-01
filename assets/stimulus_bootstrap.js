import { startStimulusApp } from '@symfony/stimulus-bundle';
import NewMenuController from './controllers/new_menu_controller.js';
import FileUploadController from './controllers/file_upload_controller.js';

const app = startStimulusApp();
app.register('new-menu', NewMenuController);
app.register('file-upload', FileUploadController);
