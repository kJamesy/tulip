<template>
    <div class="mt-3">
        <div class="sk-spinner sk-spinner-pulse bg-gray-800" v-if="fetchingData"></div>
        <div v-if="! fetchingData && appResourceCount">
            <div v-if="appUserHasPermission('read')">
                <a href="#" v-on:click.prevent="exportAll" class="btn btn-link pull-right" title="Export All" data-toggle="tooltip"><i class="icon ion-android-download"></i></a>
                <div class="clearfix mb-2"></div>
                <div class="row">
                    <div class="col-md-6">
                        <form v-on:submit.prevent="appDoSearch">
                            <label class="form-control-label">&nbsp;</label>
                            <div class="form-group">
                                <input type="text" v-model.trim="appSearchText" placeholder="Search" class="form-control" />
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form>
                            <label class="form-control-label" for="mailing_lists">Mailing List</label>
                            <select class="custom-select form-control" v-model="mailingList" id="mailing_lists">
                                <option value="0">(All)</option>
                                <option v-for="mList in mailingLists" v-bind:value="mList.id">
                                    {{ mList.label }}
                                </option>
                                <option value="-1">(Unattached)</option>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="mt-4 mb-4">
                    <form class="form-inline pull-left" v-if="appSelectedResources.length">
                        <label class="form-control-label mr-sm-2" for="quick-edit">Options</label>
                        <select class="custom-select form-control mb-2 mb-sm-0 mr-sm-5" v-model="appQuickEditOption" id="quick-edit">
                            <option v-for="option in quickEditOptions" v-bind:value="option.value" v-if="appUserHasPermission(option.value)">
                                {{ option.text }}
                            </option>
                        </select>
                    </form>
                    <form class="form-inline pull-right">
                        <span class="mr-3">Page {{ appPagination.current_page }} of {{ appPagination.last_page }} [<b>{{ appPagination.total }} items</b>]</span>
                        <label class="form-control-label mr-sm-2" for="records_per_page">Per Page</label>
                        <select class="custom-select form-control mb-2 mb-sm-0" v-model="appPerPage" id="records_per_page">
                            <option v-for="option in appPerPageOptions" v-bind:value="option.value">
                                {{ option.text }}
                            </option>
                        </select>
                    </form>
                    <div class="clearfix"></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-info">
                        <thead>
                            <tr class="pointer-cursor">
                                <th class="normal-cursor checkbox-th" v-if="appUserHasPermission('update')">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="selectAllCheckbox" v-model="appSelectAll">
                                        <label class="custom-control-label" for="selectAllCheckbox"></label>
                                    </div>
                                </th>
                                <th v-on:click.prevent="appChangeSort('first_name')">First Name <span v-html="appGetSortMarkup('first_name')"></span></th>
                                <th v-on:click.prevent="appChangeSort('last_name')">Last Name <span v-html="appGetSortMarkup('last_name')"></span></th>
                                <th v-on:click.prevent="appChangeSort('email')">Email <span v-html="appGetSortMarkup('email')"></span></th>
                                <th v-on:click.prevent="appChangeSort('active')">Active <span v-html="appGetSortMarkup('active')"></span></th>
                                <th v-on:click.prevent="appChangeSort('consent')">Consented <span v-html="appGetSortMarkup('consent')"></span></th>
                                <th v-on:click.prevent="appChangeSort('reviewed_at')" >Reviewed <span v-html="appGetSortMarkup('reviewed_at')"></span></th>
                                <th v-on:click.prevent="appChangeSort('updated_at')" >Updated <span v-html="appGetSortMarkup('updated_at')"></span></th>
                                <th v-if="appUserHasPermission('read')"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="resource in orderedAppResources">
                                <td v-if="appUserHasPermission('update')">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" v-bind:id="'select_' + resource.id" v-model="appSelectedResources" v-bind:value="resource.id">
                                        <label class="custom-control-label" v-bind:for="'select_' + resource.id"></label>
                                    </div>
                                </td>
                                <td>{{ resource.first_name }}</td>
                                <td>{{ resource.last_name }}</td>
                                <td>{{ resource.email }}</td>
                                <td v-html="appActiveMarkup(resource.active)"></td>
                                <td v-html="appConsentMarkup(resource.consent)"></td>
                                <td><span v-bind:title="resource.reviewed_at | dateToTheMinWithDayOfWeek" data-toggle="tooltip">{{ resource.reviewed_at | dateToTheDay }}</span></td>
                                <td><span v-bind:title="resource.updated_at | dateToTheMinWithDayOfWeek" data-toggle="tooltip">{{ resource.updated_at | dateToTheDay }}</span></td>
                                <td v-if="appUserHasPermission('read')">
                                    <router-link v-bind:to="{ name: 'admin_subscribers.view', params: { id: resource.id }}" class="btn btn-sm rounded-circle btn-pink"><i class="icon ion-eye"></i></router-link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <pagination :pagination="appPagination" :callback="fetchResources" :options="appPaginationOptions" class="mt-5 mb-3"></pagination>

                <div class="modal fade" id="attachModal">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Attach Subscribers to Mailing List</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <form v-on:submit.prevent="attachToMailingList">
                                    <label class="form-control-label mr-sm-2" for="attach_to_mailing_list">
                                        Select Mailing List
                                    </label>
                                    <select class="custom-select form-control" v-model="attachTo" id="attach_to_mailing_list">
                                        <option value="0" disabled>(Select)</option>
                                        <option v-for="mList in attachableMailingLists" v-bind:value="mList.id" v-if="mList.id !== mailingList">
                                            {{ mList.name }}
                                        </option>
                                    </select>
                                    <button class="btn btn-pink mt-3" v-bind:disabled="! allowAttaching()">Attach</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="! appUserHasPermission('read')">
                <i class="icon ion-alert"></i> {{ appUnauthorisedErrorMessage }}
            </div>
        </div>
        <div v-if="! fetchingData && ! appResourceCount" class="mt-5">
            No items found
        </div>
        <div class="mt-3 mb-3 font-italic text-right" v-if="! fetchingData && appDeletedNum && ! parseInt(belongingTo)">
            <router-link v-bind:to="{ name: 'admin_subscribers.trash'}" class="btn btn-link"><i class="icon ion-trash-a"></i> Deleted Items ({{ appDeletedNum }})</router-link>
        </div>

    </div>
</template>

<script>
    export default {
        mounted() {
            this.$nextTick(function() {
                this.appInitialiseSettings();
                this.appInitialiseTooltip();
                this.belongingTo = this.appBelongingToOrUnattached;
                this.fetchResources();
                this.applyListeners();
            });
        },
        data() {
            return {
                fetchingData: true,
                quickEditOptions: [
                    { text: 'Select Option', value: '' },
                    { text: 'Activate', value: 'activate' },
                    { text: 'Deactivate', value: 'deactivate' },
                    { text: 'Attach to Mailing List', value: 'attach' },
                    { text: 'Detach from All Mailing Lists', value: 'detach' },
                    { text: 'Export', value: 'export' },
                    { text: 'Delete', value: 'delete' }
                ],
                mailingList: 0,
                mailingLists: [],
                attachableMailingLists: [],
                attachTo: 0
            }
        },
        methods: {
            fetchResources(orderAttr, orderToggle) {
                this.appFetchResources(this, orderAttr, orderToggle);
            },
            quickEditResources() {
                this.appQuickEditResources();
            },
            exportAll() {
                this.appExportAll();
            },
            setOtherData() {
                let vm = this;
                if ( typeof vm.appFetchResponse !== 'undefined' ) {
                    let response = vm.appFetchResponse;

                    if ( response.data.mailingLists )
                        vm.mailingLists = response.data.mailingLists;
                    if ( response.data.attachableMailingLists )
                        vm.attachableMailingLists = response.data.attachableMailingLists;
                    if ( response.data.mailingList )
                        vm.mailingList = response.data.mailingList.id;
                    else if ( vm.appUnattached )
                        vm.mailingList = -1;
                }
            },
            selectAttachableMailingList() {
                let vm = this;

                $('#attachModal').modal('show');

                $('#attachModal').on('hidden.bs.modal', function(e) {
                    vm.attachTo = 0;
                    vm.appQuickEditOption = '';
                });
            },
            attachToMailingList() {
                let vm = this;
                let current = parseInt(vm.mailingList);
                let selected = parseInt(vm.attachTo);

                if ( selected ) {
                    if ( selected !== current ) {
                        $('#attachModal').modal('hide');
                        vm.quickEditResources();
                    }
                }
            },
            allowAttaching() {
                return ( parseInt(this.attachTo) && (parseInt(this.attachTo) !== parseInt(this.mailingList)) );
            },
            applyListeners() {
                let vm = this;

                vm.$on('successfulfetch', function () {
                    vm.setOtherData();
                });

                vm.$on('attaching', function () {
                    vm.selectAttachableMailingList();
                });
            }
        },
        watch: {
            mailingList(newVal) {
                let vm = this;

                if ( parseInt(newVal) > 0 )
                    vm.$router.push({ name: 'admin_subscribers.list', params: {mListId: parseInt(newVal)} });
                else if ( parseInt(newVal) === -1 )
                    vm.$router.push({ name: 'admin_subscribers.unattached' });
                else
                    vm.$router.push({ name: 'admin_subscribers.index' });
            }
        },
    }
</script>
