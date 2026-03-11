
<?php


use App\Http\Controllers\API\ServetelController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BOMController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SMSController;
use App\Http\Controllers\Sungrace;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\FED\TicketController;
use App\Http\Controllers\FED\MissedCallController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\FED\CommentsController;
use App\Http\Controllers\FED\TicketTypeController;
use App\Http\Controllers\FED\VoiceController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\WorkflowController;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;
use App\Models\Organisation;
use App\Http\Controllers\FED\Genaratepdf;
use App\Http\Controllers\Test\CoconutController;
use App\Http\Controllers\TaskBoardController;
use App\Http\Controllers\FED\WorkLogController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DueController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RfqController;
use App\Http\Controllers\SupplierConfigurationController;
use App\Http\Controllers\SupplierQuoteController;

use App\Http\Controllers\BomUploadController;
use App\Http\Controllers\RFQUploadsController;
use App\Http\Controllers\ViewBOMController;
use App\Http\Controllers\BlockthreeController;

use App\Http\Controllers\BomAuditController;
use App\Http\Controllers\BomAuditGraphController;
use App\Http\Controllers\BomMatrixJsonController;
use App\Http\Controllers\BomFloatingStatusController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QWController;
use App\Http\Controllers\MissingNotesController;
use App\Http\Controllers\PriceControlController;
use App\Http\Controllers\API\BomExcelApiController;
use App\Http\Controllers\Api\BomJsonApiController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SmtpDetailController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Auth::routes();
Route::middleware('auth')->group(function () {

	// Route::get('/', [BoardController::class, 'index']);
	// Route::get('/dashboard', [BoardController::class, 'index']);

	// Route::get('/tickets/{id}', [TicketController::class,'show'])->name('tickets.show');
	Route::get('/welcome', function () {
		return view('welcome');
	});
	Route::get('/oldhome', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
	Route::get('/calls/view', [HomeController::class, 'viewCalls']);

	//SMS Integeration test route
	Route::get('/sms', [HomeController::class, 'sms']);

	Route::get('/phonebook', [App\Http\Controllers\Sungrace::class, 'viewPhonebook']);
	Route::post('/phonebook/edit', [App\Http\Controllers\Sungrace::class, 'editPhonebook']);
	Route::get('/crm', function () {
		return view('crm/home');
	});
	Route::resource('tickets', TicketController::class);
	Route::resource('tickettypes', TicketTypeController::class);
	Route::get('/orgtickets', [TicketController::class, 'viewOrgTickets'])->name('tickets.orgtickets');
	Route::resource('permissions', PermissionController::class);
	Route::resource('roles', RoleController::class);
	Route::resource('organisations', OrganisationController::class);
	Route::resource('teams', TeamController::class);
	Route::resource('invoices', InvoiceController::class);
	Route::resource('dashboard', BoardController::class);
	Route::resource('myboard', TaskBoardController::class);
	Route::get('/missedcalls/{id}/accept', [MissedCallController::class, 'accept'])->name('missedcalls.accept');
	Route::resource('missedcalls', MissedCallController::class);
	Route::get('/cdash', [MissedCallController::class, 'callsdashboard']);

	Route::get('/listmcalls/{parameter}', [MissedCallController::class, 'showList'])
		->name('listmcalls.list');
	Route::get('/cdash', [MissedCallController::class, 'callsdashboard']);
	Route::get('/cdash', [MissedCallController::class, 'callsdashboard'])->name('home.dashboard');
	Route::get('/adduser', function () {
		return view('adduser');
	});
	Route::resource('/users', UserController::class);
	Route::get('/tickets/{ticket}/clone', [TicketController::class, 'showCloneForm'])->name('tickets.cloneForm');
	Route::post('/tickets/{ticket}/clone', [TicketController::class, 'cloneTicket'])->name('tickets.clone');

	Route::resource('comment', CommentsController::class);
	Route::resource('status', StatusController::class);

	Route::post('/tickets/{ticket}/comments', [CommentsController::class, 'store'])->name('comments.store');
	Route::resource('status', StatusController::class);
	Route::post('/tickets/{ticket}/comments', [CommentsController::class, 'store'])->name('comments.store');
	Route::put('/tickets/{ticket}/comments/{commentId}', [CommentsController::class, 'update'])->name('comments.update');



	Route::post('/record/upload', [VoiceController::class, 'store']);
	Route::get('/contact', function () {
		return view('contact');
	});
	Route::post('/send', [MailController::class, 'sendContactMail'])->name('send.contact_mail');
	Route::post('/send', [MailController::class, 'sendContactMail'])->name('send.contact_mail');

	Route::resource('workflows', WorkflowController::class);


	Route::resource('/my-profile', ProfileController::class);
	Route::get('/generatepdf/{id}', [Genaratepdf::class, 'generatepdf'])->name('invoice.show');

	Route::resource('coconut', CoconutController::class);

	Route::resource('worklogs', WorkLogController::class);

	// routes/web.php


	Route::delete('/invoiceItems/{id}', [InvoiceController::class, 'deleteInvoiceItem'])->name('invoiceItems.destroy');
	Route::resource('/report', ReportController::class);
	Route::resource('/due', DueController::class);
	Route::get('/generate-report-pdf', [ReportController::class, 'generateReportPdf'])->name('generateReportPdf');

	Route::get('/generate-Due_report-pdf', [DueController::class, 'generateReportPdf'])->name('generateDueReportPdf');

	Route::get('teams/{team}/users', [TeamController::class, 'showUsers'])->name('teams.users.index');

	Route::resource('/payments', PaymentController::class);

	Route::get('payments/create/{invoice_id}', [PaymentController::class, 'create'])->name('payments.create');

	Route::get('/CAMind', [DashboardController::class, 'index']);
	Route::get('/', [DashboardController::class, 'dashboard']);
	Route::resource('/dashboard', DashboardController::class);
	Route::get('/get-billable-tasks', [InvoiceController::class, 'getBillableTasks'])->name('get-billable-tasks');
	Route::get('/ticket/attachment/download/{id}', [TicketController::class, 'downloadStore'])->name('ticket.attachment.downloadStore');

	Route::get('/bom/view/{type?}', [BOMController::class, 'index'])->name('bom.filter');
	Route::resource('/bom', BOMController::class);
	// Route::resource('/boms', ViewBOMController::class);
	Route::resource('/rfqs', RFQUploadsController::class);
	Route::get('/bom/download/json/{id}', [BOMController::class, 'downloadJson'])->name('bom.download.json');
	Route::get('/bom/download/file/{id}', [BOMController::class, 'downloadFile'])->name('bom.download.file');
	Route::get('/bom/bom-matrix-pn/{id}', [BlockthreeController::class, 'cpnvsmpn'])->name('bom.bom-matrix-pn');
	Route::get('/bom/faiChargeSheet/{id}', [BlockthreeController::class, 'faiCharge'])->name('bom.faiCharge');
	Route::get('/bom/download-faiCharge/{id}', [BlockthreeController::class, 'faiChargeSheet'])->name('bom.faiChargeSheet');
	Route::get('/bom/mech-nrecharge/{id}', [BlockthreeController::class, 'nrecharge'])->name('bom.mech-nrecharge');
	Route::get('/bom/download-mech-nrecharge/{id}', [BlockthreeController::class, 'nrecharge_excel'])->name('bom.download-mech-nrecharge');
	Route::get('/bom/lead-fg/{id}', [BlockthreeController::class, 'lead_time_fg'])->name('bom.lead-fg');
	Route::get('/bom/jsontoExcel/{id}', [BlockthreeController::class, 'jsontoExcel'])->name('bom.jsontoExcel');
	Route::get('/bom/download-lead-time-consolidated/{bomUploadId}', [BlockthreeController::class, 'download_lead_time_consolidate_excel']);
	Route::get('/bom/download-fg-lead-time/excel/{bomUploadId}', [BlockthreeController::class, 'download_fg_lead_time_excel']);
	Route::get('/bom/lead-fg-cons/{id}', [BlockthreeController::class, 'lead_time_consolidate'])->name('bom.lead-cons');
	Route::get('/bom/missing/{id}', [MissingNotesController::class, 'missingNotes'])->name('bom.missing');
	Route::get('/download-uncosted/{bomUploadId}', [MissingNotesController::class, 'jsontoExcel']);
	Route::get('/missing-notes/export/{bomUploadId}', [MissingNotesController::class, 'exportAllMissingNotes']);
	Route::get('/processTab5/{bomUploadId}', [HeavyComputeService::class, 'processTab5']);
	Route::get('/test-control/{bomUploadId}', [PriceControlController::class, 'processPriceControl']);
	Route::get('/price-control/download/{bomUploadId}', [PriceControlController::class, 'downloadPriceControlExcel']);

	Route::get('/bom/download-sum-and-count/excel/{bomUploadId}', [BlockthreeController::class, 'download_sum_ad_count']);
	Route::get('/bom/download-sum-count-details/excel/{bomUploadId}', [BlockthreeController::class, 'download_sum_count_details']);
	Route::get('/bom/sun_and_count/{id}', [BlockthreeController::class, 'sun_and_count'])->name('bom.sun_and_count');
	Route::get('/bom/sum_and_count_details/{id}', [BlockthreeController::class, 'sum_and_count_detail']);
	Route::get('/bom/revision_history/{id}', [BlockthreeController::class, 'revision_history_json']);
	Route::get('/bom/download-revision-history/excel/{bomUploadId}', [BlockthreeController::class, 'download_revision_history']);


	Route::get('/bom/part_mfg/{id}', [BlockthreeController::class, 'cpnvsmpn_json']);
	Route::get('/bom/aclass-json/{id}', [BlockthreeController::class, 'aclass_json']);

	Route::get('/bom/download/{project_id}/{version}', [BOMController::class, 'downloadVersion'])->name('bom.download.version');

	Route::get('/rfq/create/{bomUpload}', [RfqController::class, 'create'])->name('rfq.create')->middleware('auth');
	Route::post('/rfq/send/{bomUpload}', [RfqController::class, 'send'])->name('rfq.send')->middleware('auth');



	// Route::get('/bom/{id}/version-history', [BOMController::class, 'versionHistory'])->name('bom.versionHistory');

	// Route::post('/bom/update/{projectId}', [BomController::class, 'updateBOM'])->name('bom.updateBOM');
	// Route::get('/bom/{projectId}/edit', [BOMController::class, 'edit'])->name('bom.edit');
	Route::post('/bom/update/{bomUploadId}', [BomController::class, 'updateBOM'])->name('bom.updateBOM');
	Route::get('/bom/{bomUploadId}/edit', [BomController::class, 'edit'])->name('bom.edit');
	Route::get('/bom/version-history/{bomUploadId}', [BomController::class, 'versionHistory'])->name('bom.versionHistory');
	Route::get('/bom/{bomId}/floating-status', [BomFloatingStatusController::class, 'show'])->name('bom.floating_status.show');

	// Route::get('/bom-upload', [BomUploadController::class, 'index'])->name('bom.index');
	// Route::get('/bom-view', [BomUploadController::class, 'view'])->name('bom.view');
	// Route::post('/bom', [BomUploadController::class, 'store'])->name('bom.store');

	Route::get('/supplier-Configuration/{bom_id}', [SupplierConfigurationController::class, 'populateBomSupplierMpn'])->name('supplierConfig.form');
	Route::post('/submit-form/{bom_id}', [SupplierConfigurationController::class, 'submitForm'])->name('supplierConfig.submitForm');
	Route::get('download/{filename}', [SupplierConfigurationController::class, 'download'])->name('download');
	// Route::get('/supplier-quote/{project_id}', [SupplierQuoteController::class, 'index'])->name('supplier-quote.index');
	Route::get('/supplier-quote/{project_id}/{bom_id}', [SupplierQuoteController::class, 'index'])->name('supplier-quote.index');
	Route::get('/supplier-quotes/view/{supplier_id}/{project_id}/{bom_id}', [SupplierQuoteController::class, 'view'])->name('supplier-quotes.view');
	Route::get('/supplier-quotes/resend', [SupplierQuoteController::class, 'resend'])->name('supplier-quotes.resend');
	Route::put('/supplier-quote/{supplier_id}/contact-email', [SupplierQuoteController::class, 'updateContactEmail'])->name('supplier-quote.contact-email.update');


	Route::get('/bom/audit/{id}', [BomAuditController::class, 'show'])->name('crm.BOM.BOM-SourceEngineer.auditBOM');
	Route::get('/bom/audit/{id}/pdf', [BomAuditController::class, 'downloadAuditPDF'])->name('crm.BOM.BOM-SourceEngineer.auditPDF');


	Route::resource('/projects', ProjectController::class);

	Route::get('/rfqs/create/{pid}/{bid}/{sid}', [RFQUploadsController::class, 'create'])->name('rfqs.create');

	Route::get('/bom/generate-matrix/{id}', [BOMController::class, 'generateMatrixBOM'])->name('bom.generateMatrix');
	Route::get('/create/{project_id}/{bom_id}/{supplier_id}', [SupplierConfigurationController::class, 'handleCreateRedirect']);


	Route::get('/qw/create/{project_id}/{bom_id}', [QWController::class, 'create'])->name('qw.create');

	Route::post('/qw/store', [QWController::class, 'store'])->name('qw.store');
	Route::get('/matrixs-bom/{bomUploadId}', [BomController::class, 'exportBomMatrix']);
	Route::post('/update-volume-quantities', [SupplierConfigurationController::class, 'updateVolumeQuantities'])->name('update.volume.quantities');
	Route::post('/bom/verify-template', [BOMController::class, 'verifyTemplate'])->name('bom.verify');

	Route::get('/suppliers/import/template', [SupplierController::class, 'downloadTemplate'])->name('suppliers.import.template');
	Route::post('/suppliers/import/verify', [SupplierController::class, 'verifyImport'])->name('suppliers.import.verify');
	Route::post('/suppliers/import', [SupplierController::class, 'import'])->name('suppliers.import');

	Route::resource('suppliers', SupplierController::class)->names([
    'index'   => 'suppliers.index',
    'create'  => 'suppliers.create',
    'store'   => 'suppliers.store',
    'edit'    => 'suppliers.edit',
    'update'  => 'suppliers.update',
    'destroy' => 'suppliers.destroy',
]);

	Route::post('/smtp-details/import', [SmtpDetailController::class, 'import'])->name('smtp-details.import');

	Route::resource('smtp-details', SmtpDetailController::class)->names([
    'index'   => 'smtp-details.index',
    'create'  => 'smtp-details.create',
    'store'   => 'smtp-details.store',
    'edit'    => 'smtp-details.edit',
    'update'  => 'smtp-details.update',
    'destroy' => 'smtp-details.destroy',
]);

	Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
	Route::post('/activity-logs/track', [ActivityLogController::class, 'track'])->name('activity-logs.track');

});
Route::get('/rfqs/create/generating', [RFQUploadsController::class, 'errorHandler'])->name('rfqs.errorhandler');

Route::get('/rfqs/create/{encryptedToken}', [RFQUploadsController::class, 'create'])->name('rfqs.create');

Route::get('rfq/download', [BOMController::class, 'download'])->name('rfq.download');
Route::post('/rfqs/store/{pid}/{bid}/{sid}', [RFQUploadsController::class, 'store'])->name('rfqs.store');
Route::get('/test-419', function () {
	abort(419);
});

Route::get('/bom/audit/graph/{bid}', [BomAuditGraphController::class, 'graph'])->name('bom.audit.graph');
// Route::get('/generate-excel', [BomExcelApiController::class, 'generateExcel']);

Route::get('/bom-matrix/generate/{bomUploadId}', [BomMatrixJsonController::class, 'generate']);
Route::get('/bom-matrix/export/{bomUploadId}', [BomMatrixJsonController::class, 'exportBomMatrix'])->name('bom.matrix.export');

Route::get('/cbom/generate/{bomId}', [BomMatrixJsonController::class, 'generateCBomJson']);

Route::get('/cbom/export/{bomId}', [BomMatrixJsonController::class, 'exportCBOMExcel']);

Route::get('/test/job1', [BomJsonApiController::class, 'index']);
// Route::get('/download/mb/{id}', [BomExcelApiController::class, 'generateExcel']);
Route::get('/download/mb/{id}', [BomExcelApiController::class, 'downloadFromS3'])
    ->name('bom.download');

Route::get('/bom/{id}/export/status', [BomExcelApiController::class, 'exportStatus'])->name('bom.export.status');

Route::get('/summary/generate/{bomId}', [BomMatrixJsonController::class, 'generateSummaryJsonByAssembly']);
Route::get('/summary/export/{bomId}', [BomMatrixJsonController::class, 'exportSummaryExcel']);

 Route::get('/excess-inv/generate/{bomId}', [BomMatrixJsonController::class, 'generateExcessInventoryJson']);
 Route::get('/excess-inv/export/{bomId}', [BomMatrixJsonController::class, 'exportExcessInvExcel']);

 Route::get('/batchwise-cashflow/generate/{bomId}', [BomMatrixJsonController::class, 'generateBatchwiseCashflowJson']);
 Route::get('/batchwise-cashflow/export/{bomId}', [BomMatrixJsonController::class, 'exportBatchwiseCashflowInvExcel']);

  Route::get('/crm/quotation/view/{bom_id}', [SupplierConfigurationController::class, 'view'])
        ->name('crm.quotation.view');
 
 
