<?php

namespace App\Http\Controllers\Admin;

use App\Exporters\ResourceExporter;
use App\Helpers\Hashids;
use App\Importers\ResourceImporter;
use App\MailingList;
use App\Permissions\UserPermissions;
use App\Settings\UserSettings;
use App\Subscriber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SubscriberController extends Controller
{
	protected $redirect;
	public $rules;
	public $perPage;
	public $orderByFields;
	public $orderCriteria;
	protected $settingsKey;
	protected $policies;
	protected $policyOwnerClass;
	protected $permissionsKey;
	protected $friendlyName;
	protected $friendlyNamePlural;
	protected $allowedFileExts;
	protected $excelImportColumns;
	protected $uploadStoragePath;

	/**
	 * SubscriberController constructor.
	 */
	public function __construct()
	{
		$this->redirect = route('admin.home');
		$this->rules = Subscriber::$rules;
		$this->perPage = 25;
		$this->orderByFields = ['first_name', 'last_name', 'email', 'active', 'consent', 'reviewed_at', 'created_at', 'updated_at'];
		$this->orderCriteria = ['asc', 'desc'];
		$this->settingsKey = 'subscribers';
		$this->policies = UserPermissions::getPolicies();
		$this->policyOwnerClass = Subscriber::class;
		$this->permissionsKey = UserPermissions::getModelShortName($this->policyOwnerClass);
		$this->friendlyName = 'Subscriber';
		$this->friendlyNamePlural = 'Subscribers';
		$this->allowedFileExts = ['xlt', 'xls', 'xlsx'];
		$this->excelImportColumns = ['first_name' => 'first_name', 'last_name' => 'last_name', 'email' => 'email'];
		$this->uploadStoragePath = storage_path('app/uploads');
	}

	/**
	 * Display a listing of the resource.
	 * @param Request $request
	 * @return $this|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
	public function index(Request $request)
	{
		if ( $request->user()->can('read', $this->policyOwnerClass) ) {
			$user = $request->user();

			$orderBy = in_array(strtolower($request->orderBy), $this->orderByFields) ? strtolower($request->orderBy) : $this->orderByFields[0];
			$order = in_array(strtolower($request->order), $this->orderCriteria) ? strtolower($request->order) : $this->orderCriteria[1];
			$perPage = (int) $request->perPage ?: $this->perPage;
			$deleted = (int) $request->trash;

			if ( ! $request->ajax() ) {
				return view('admin.subscribers')->with(['settingsKey' => $this->settingsKey, 'permissionsKey' => $this->permissionsKey, 'activeGroup' => 'recipients']);
			}
			else {
				$settings = UserSettings::getSettings($user->id, $this->settingsKey, $orderBy, $order, $perPage, true);
				$belongingTo = (int) $request->belongingTo;
				$search = strtolower($request->search);

				$resources = $search
					? Subscriber::getSearchResults($search, $belongingTo, $deleted, $settings["{$this->settingsKey}_per_page"] )
					: Subscriber::getResources($belongingTo, [], $deleted, $settings["{$this->settingsKey}_order_by"], $settings["{$this->settingsKey}_order"], $settings["{$this->settingsKey}_per_page"] );

				$deletedNum = Subscriber::getCount(1);
				$mailingList = $belongingTo ? MailingList::findResource($belongingTo) : null;
				$mailingLists = MailingList::getAttachedResources();
				$attachableMailingLists = MailingList::getAttachableResources();

				if ( $resources->count() )
					return response()->json(compact('resources', 'deletedNum', 'mailingList', 'mailingLists', 'attachableMailingLists'));
				else
					return response()->json(['error' => "No $this->friendlyNamePlural found", 'deletedNum' => $deletedNum], 404);
			}
		}
		else {
			if ( $request->ajax() )
				return response()->json(['error' => 'You are not authorised to view this page.'], 403);
			else
				return redirect($this->redirect);
		}
	}

    /**
     * Show the form for creating a new resource.
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
	    if ( $request->user()->can('create', $this->policyOwnerClass) ) {
		    $mailing_lists = MailingList::getAttachableResources();
		    return response()->json(compact('mailing_lists'));
	    }

	    return response()->json(['error' => 'You are not authorised to perform this action.'], 403);
    }

	/**
	 * Store a newly created resource in storage.
	 * @param Request $request
	 * @return Subscriber|\Illuminate\Http\JsonResponse
	 */
    public function store(Request $request)
    {
	    if ( $request->user()->can('create', $this->policyOwnerClass) ) {
		    if ( $request->has('importing') && $request->importing )
		    	return $this->handleImport($request);

		    $this->validate($request, $this->rules);

		    $resource = new Subscriber();
		    $resource->first_name = trim($request->first_name);
		    $resource->last_name = trim($request->last_name);
		    $resource->email = strtolower(trim($request->email));
		    $resource->active = $request->active ? 1 : 0;
		    $resource->save();

		    if ( $request->has('mailing_lists') && is_array($request->mailing_lists) && count($request->mailing_lists) )
			    $resource->mailing_lists()->attach($request->mailing_lists);

		    return $resource;
	    }

	    return response()->json(['error' => 'You are not authorised to perform this action.'], 403);
    }

	/**
	 * Process import
	 * @param Request $request
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
    public function handleImport(Request $request)
    {
	    if ( $request->has('finalising') && $request->finalising )
		    return $this->finaliseImport($request);

	    $file = $request->file('upload');

	    if ( ! $file || ! $file->isValid() )
		    return response()->json(['file' => ['Your file isn\'t valid. Please try again with a different file.']], 422);

	    $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);

	    if ( ! in_array($ext, $this->allowedFileExts))
		    return response()->json(['file' => ['Your file extension is not allowed. Please use a valid excel or CSV file.']], 422);

	    $fileName = time() . ".$ext";
	    $safeFileName = str_replace('.', '_', $fileName);
	    $storageDirectory = rtrim(explode('app', $this->uploadStoragePath)[1], '/');
	    $file->storeAs($storageDirectory, $fileName);
	    $filePath = "$this->uploadStoragePath/$fileName";

	    $rules = $this->rules;
	    $rules['email'] = str_replace("|unique:subscribers", '', $rules['email']);

	    $importer = new ResourceImporter($fileName, $safeFileName, $filePath, $this->excelImportColumns, $rules);
	    $excelResults = $importer->getResults('subscribers');

	    if ( $excelResults )
		    return response()->json(['success' => "Import successfully processed",
		                             'existingRows' => $excelResults->existingRows,
		                             'badRows' => $excelResults->badRows,
		                             'rowsCount' => $excelResults->rowsNum,
		                             'fileName' => $fileName]);
	    else
		    return response()->json(['error' => 'Please try the import again.'], 500);
    }

	/**
	 * Finalise the import and save the records - or cancel
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function finaliseImport(Request $request)
	{
		$active = (int) $request->active;
		$mailing_lists = (array) $request->mailing_lists;
		$fileName = $request->fileName;
		$safeFileName = $fileName ? str_replace('.', '_', $fileName) : null;
		$filePath = $fileName ? "$this->uploadStoragePath/$fileName" : '';

		if ( strtolower($request->action) === 'cancel' ) {
			if ( $safeFileName ) {
				unlink($filePath);
				cache()->forget("resultsOfImport$safeFileName");
			}

			return response()->json(['cancellation' => "Import successfully cancelled"]);
		}
		elseif ( strtolower($request->action) === 'proceed' ) {
			$rules = $this->rules;
			$rules['email'] = str_replace("|unique:subscribers", '', $rules['email']);

			$importer = new ResourceImporter($fileName, $safeFileName, $filePath, $this->excelImportColumns, $rules);
			$excelResults = $importer->getResults('subscribers');

			if ( $safeFileName ) {
				unlink($filePath);
				cache()->forget("resultsOfImport$safeFileName");
			}

			$failedNum = 0;
			$existingNum = 0;
			$succeededNum = 0;

			if ( $excelResults ) {
				if ( count($excelResults->goodOnes) ) {

					if ( ! count($mailing_lists) ) { //If user didn't choose a mailing list, create a time-stamped one for them
						$time = Carbon::now()->toDateTimeString();

						$defaultMList = new MailingList();
						$defaultMList->name = "Import - $time";
						$defaultMList->description = "System-created mailing list for subscribers imported at $time";
						$defaultMList->save();

						$mailing_lists = [$defaultMList->id];
					}


					foreach ($excelResults->goodOnes as $row ) {
						$validator = validator()->make( (array) $row, $this->rules);

						if ( $validator->fails() ) {
							$existingSub = Subscriber::findResourceByEmail($row->email); //Attempt to find an existing subscriber and just attach them to the mailing lists

							if ( $existingSub ) {
								$existingMLists =  $existingSub->mailing_lists->pluck('id')->toArray();

								foreach( $mailing_lists as $mListId ) {
									if ( ! in_array($mListId, $existingMLists) )
										$existingMLists[] = $mListId;
								}

								$existingSub->mailing_lists()->sync($existingMLists);
								$existingSub->touch();
								$existingNum++;
							}
							else
								$failedNum++;
						}
						else {
							$subscriber = new Subscriber();
							$subscriber->first_name = $row->first_name;
							$subscriber->last_name = $row->last_name;
							$subscriber->email = $row->email;
							$subscriber->active = $active ? 1 : 0;
							$subscriber->save();

							$subscriber->mailing_lists()->sync($mailing_lists);

							$succeededNum++;
						}
					}

					return response()->json(['success' => "Imported subscribers successfully saved.", 'failedNum' => $failedNum, 'existingNum' => $existingNum, 'succeededNum' => $succeededNum]);
				}
				else
					return response()->json(['no_good_records' => 'None of the rows in the file passed validation. Please check and try again.'], 422);
			}
			else
				return response()->json(['file_does_not_exist' => 'Please try the import again.'], 500);
		}
		else
			return response()->json(['error' => 'Please try the import again.'], 500);
	}

	/**
	 * Show specified resource.
	 * @param $id
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
    public function show($id, Request $request)
    {
	    $resource = Subscriber::findResource( (int) $id );
	    $currentUser = $request->user();

	    if ( $resource ) {
		    if ( ! $currentUser->can('read', $this->policyOwnerClass) )
			    return response()->json(['error' => 'You are not authorised to perform this action.'], 403);

		    return response()->json(compact('resource'));
	    }

	    return response()->json(['error' => "$this->friendlyName does not exist"], 404);
    }

	/**
	 * Show resource for editing
	 * @param $id
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function edit($id, Request $request)
	{
		$resource = Subscriber::findResource( (int) $id );
		$currentUser = $request->user();

		if ( $resource ) {
			if ( ! $currentUser->can('update', $this->policyOwnerClass) )
				return response()->json(['error' => 'You are not authorised to perform this action.'], 403);

			$mailing_lists = MailingList::getAttachableResources();

			return response()->json(compact('resource', 'mailing_lists'));
		}

		return response()->json(['error' => "$this->friendlyName does not exist"], 404);
	}

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
	    $resource = Subscriber::findResource( (int) $id );
	    $currentUser = $request->user();

	    if ( $resource ) {
		    if ( ! $currentUser->can('update', $this->policyOwnerClass) )
			    return response()->json(['error' => 'You are not authorised to perform this action.'], 403);

		    $rules = $this->rules;

		    if ( strtolower($resource->email) === strtolower(trim($request->email)) )
			    unset($rules['email']);

		    $this->validate($request, $rules);

		    $resource->first_name = trim($request->first_name);
		    $resource->last_name = trim($request->last_name);
		    $resource->email = strtolower(trim($request->email));
		    $resource->active = $request->active ? 1 : 0;
		    $resource->save();

		    if ( $request->has('mailing_lists') ) {
			    $resource->mailing_lists()->sync($request->mailing_lists);
			    $resource->touch();
		    }

		    return response()->json(compact('resource'));
	    }

	    return response()->json(['error' => "$this->friendlyName does not exist"], 404);
    }

	/**
	 * Delete/destroy the specified resource
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id, Request $request)
	{
		$resource = Subscriber::findResource( (int) $id );
		$currentUser = $request->user();

		if ( $currentUser->can('delete', $this->policyOwnerClass) ) {
			if ( ! $resource )
				return response()->json(['error' => "$this->friendlyName does not exist"], 404);

			$suffix = 'permanently deleted';

			if ( $request->permanent )
				$resource->delete();
			else {
				$resource->is_deleted = 1;
				$resource->save();
				$suffix = 'moved to trash';
			}

			return response()->json(['success' => "$this->friendlyName $suffix"]);
		}

		return response()->json(['error' => 'You are not authorised to perform this action.'], 403);
	}

	/**
	 * Quickly update resources in bulk
	 * @param Request $request
	 * @param $update
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function quickUpdate(Request $request, $update)
	{
		$currentUser = $request->user();
		$resourceIds = $request->resources;

		if ( $currentUser->can('delete', $this->policyOwnerClass) ) {
			$selectedNum = count($resourceIds);

			if ( $selectedNum ) {
				try {
					$resources = Subscriber::getResources(0, $resourceIds, - 1 )->pluck('id')->toArray();
					$attachTo = (int) $request->attachTo;
					$successNum = 0;

					if ( $resources ) {
						switch ($update) {
							case 'activate':
								$successNum = Subscriber::doBulkActions($resources, 'activate');
								break;
							case 'deactivate':
								$successNum = Subscriber::doBulkActions($resources, 'deactivate');
								break;
							case 'attach':
								if ( $attachTo ) {
									foreach( $resources as $resourceId ) {
										$sub = Subscriber::findResource($resourceId);

										if ( ! in_array($attachTo, $sub->mailing_lists->pluck('id')->toArray()) ) {
											$sub->mailing_lists()->attach($attachTo);
											$sub->touch();
										}

										$successNum++;
									}
								}
								break;
							case 'detach':
								foreach( $resources as $resourceId ) {
									$sub = Subscriber::findResource($resourceId);
									$sub->mailing_lists()->detach();
									$sub->touch();

									$successNum++;
								}
								break;
							case 'delete':
								$successNum = Subscriber::doBulkActions($resources, 'delete');
								break;
							case 'restore':
								$successNum = Subscriber::doBulkActions($resources, 'restore');
								break;
							case 'destroy':
								$successNum = Subscriber::doBulkActions($resources, 'destroy');
								break;
						}

						$append = ( $selectedNum == $successNum ) ? '' : "Please note you do not have sufficient permissions to $update some $this->friendlyNamePlural.";
						$string = $successNum == 1 ? $this->friendlyName : $this->friendlyNamePlural;
						$successNum = $successNum ?: 'No';

						if ( $update == 'delete')
							$update = 'moved to trash';
						else if ( $update == 'destroy')
							$update = 'permanently deleted';
						else if ( $update == 'attach')
							$update = 'attached to mailing list';
						else if ( $update == 'detach')
							$update = 'detached from all mailing lists';
						else
							$update = "{$update}d";

						return response()->json(['success' => "$successNum $string $update. $append"]);
					}
					else
						return response()->json(['error' => "$this->friendlyNamePlural do not exist"], 404);
				}
				catch (\Exception $e) {
					return response()->json(['error' => 'A server error occurred.'], 500);
				}
			}
			else
				return response()->json(['error' => "No $this->friendlyNamePlural received"], 500);
		}

		return response()->json(['error' => 'You are not authorised to perform this action.'], 403);
	}

	/**
	 * Export resources to Excel
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse|mixed
	 */
	public function export(Request $request)
	{
		if ( $request->user()->can('read', $this->policyOwnerClass) ) {
			$resourceIds = (array) $request->resourceIds;
			$fileName = '';

			$belongingTo = (int) $request->belongingTo;
			$deleted = $request->has('trash') ? (int) $request->trash : -1;

			$resources = Subscriber::getResources($belongingTo, $resourceIds, $deleted );

			$fileName .= count($resourceIds) || $belongingTo ? 'Specified-Items-' : 'All-Items-';
			$fileName .= Carbon::now()->toDateString();

			$exporter = new ResourceExporter($resources, $fileName);
			return $exporter->generateExcelExport('subscribers');
		}
		else
			return redirect()->back();
	}

}
