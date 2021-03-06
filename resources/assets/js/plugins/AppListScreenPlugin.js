'use strict';

const AppListScreenPlugin = {

    install(Vue, options) {

        const rootEventsHub = new Vue();

        Vue.mixin({
            mounted() {
                this.$nextTick(function() {

                });
            },
            data() {
                return {
                    appResourceUrl: window.links.base ? links.base : '',
                    appUserHome: window.links.home ? links.home : '',
                    appAdminHome: window.links.admin ? links.admin : '',
                    appUser: window.user ? user : {},
                    appPermissions: window.permissions ? permissions : {},
                    appPermissionsKey: window.permissionsKey ? permissionsKey : '',
                    appSettings: window.settings ? settings : {},
                    appSettingsKey: window.settingsKey ? settingsKey : '',
                    appFetchResponse: {},
                    appResources: [],
                    appDeletedNum: 0,
                    appOrderAttr: 'updated_at',
                    appOrder: 'desc',
                    appOrderToggle: -1,
                    appPerPage: 25,
                    appResourcesIds: [],
                    appSelectedResources: [],
                    appQuickEditOption: '',
                    appPagination: {},
                    appPaginationOptions: {
                        offset: 5,
                        alwaysShowPrevNext: true
                    },
                    appPerPageOptions: [
                        { text: '25', value: 25} ,
                        { text: '50', value: 50 },
                        { text: '100', value: 100 },
                        { text: '500', value: 500 }
                    ],
                    appSearchText: '',
                    appSearching: false,
                    appUnauthorisedErrorMessage: 'You are not authorised to view this page.',
                    rootEventsHub: rootEventsHub
                }
            },
            computed: {
                appResourceCount() {
                    return this.appResources.length;
                },
                orderedAppResources() {
                    return _.orderBy(this.appResources, [this.appOrderAttr, 'updated_at'], [( this.appOrderToggle === 1 ) ? 'asc' : 'desc', 'desc']);
                },
                appSelectAll: {
                    get() {
                        return this.appResourcesIds ? this.appSelectedResources.length === this.appResourcesIds.length : false;
                    },
                    set(value) {
                        this.appSelectedResources = value ? this.appResourcesIds : [];
                    }
                },
                appBelongingToMList() {
                    return this.$route.params.mListId ? this.$route.params.mListId: 0;
                },
                appUnattached() {
                    return _.includes(this.$route.path, 'unattached');
                },
                appBelongingToOrUnattached() {
                    if ( this.appBelongingToMList )
                       return this.$route.params.mListId;
                    else if ( this.appUnattached )
                        return -1;
                    else
                        return 0;
                },
                appImporting() {
                    return _.includes(this.$route.path, 'import');
                },
                appBelongingToUser() {
                    return this.$route.params.userId ? this.$route.params.userId: 0;
                },
                appBelongingToCampaign() {
                    return this.$route.params.campaignId ? this.$route.params.campaignId: 0;
                },
                appIsDraftsPage() {
                    return _.includes(this.$route.path, 'draft') ? 1 : 0;
                }
            },
            methods: {
                appFetchResources(vm, orderAttr, orderToggle) {
                    let progress = vm.$Progress;
                    let orderBy = orderAttr ? orderAttr : vm.appOrderAttr;
                    let orderToggle2 = orderToggle ? orderToggle : vm.appOrderToggle;
                    let lastPage = _.ceil(vm.appPagination.total / vm.appPagination.per_page);
                    let trash = (typeof vm.trash === 'undefined') ? 0 : vm.trash;
                    let belongingTo = (typeof vm.belongingTo === 'undefined') ? 0 : vm.belongingTo;

                    let params = {
                        perPage: vm.appPagination.per_page,
                        page: ( lastPage < vm.appPagination.last_page ) ? 1 : vm.appPagination.current_page,
                        orderBy: orderBy,
                        order: ( orderToggle2 === 1 ) ? 'asc' : 'desc',
                        trash: trash
                    };

                    if ( typeof vm.screen !== 'undefined' && vm.screen === 'emails' ) {
                        params.userId = vm.appBelongingToUser;
                        params.drafts = vm.appIsDraftsPage;
                        params.campaignId = vm.appBelongingToCampaign;
                    }
                    else
                        params.belongingTo = belongingTo;

                    if ( vm.appSearchText.length )
                        params.search = vm.appSearchText;

                    progress.start();
                    vm.appSelectAll = false;
                    vm.fetchingData = true;

                    vm.$http.get(vm.appResourceUrl, {params : params}).then(function(response) {
                        let resources = response.data.resources;

                        if ( resources && resources.data && resources.data.length ) {
                            vm.appResources = resources.data;
                            vm.appOrderAttr = orderBy;
                            vm.appOrderToggle = orderToggle2;
                            vm.appOrder = vm.appOrderToggle === 1 ? 'asc' : 'desc';

                            vm.$set(vm, 'appPagination', {
                                total: resources.total,
                                per_page: resources.per_page,
                                current_page: resources.current_page,
                                last_page: resources.last_page,
                                from: resources.from,
                                to: resources.to
                            });

                            vm.appResourcesIds = [];
                            _.forEach(resources.data, function(resource) {
                                vm.appResourcesIds.push(resource.id);
                            });

                            vm.appUpdateSettings();

                            progress.finish();
                            vm.fetchingData = false;
                        }
                        else {
                            let message = vm.appSearching ? 'Your search returned no results. Please try again with different keywords' : 'No records found';

                            if ( vm.appSearching )
                                vm.appCustomErrorAlertConfirmed(message);

                            vm.appSearchText = '';
                            vm.appResources = [];
                            vm.fetchingData = false;
                            progress.fail();
                        }

                        if ( response.data.deletedNum )
                            vm.appDeletedNum = response.data.deletedNum;

                        vm.appFetchResponse = response;
                        vm.$emit('successfulfetch');

                    }, function(error) {
                        if ( error.status && error.status === 403 && error.data )
                            vm.appCustomErrorAlert(error.data.error);
                        else if ( error.status && error.status === 404 && error.data ){
                            let message = vm.appSearching ? 'Your search returned no results. Please try again with different keywords' : error.data.error;

                            if ( vm.appSearching )
                                vm.appCustomErrorAlertConfirmed(message);

                            vm.appFetchResponse = error;
                            vm.$emit('successfulfetch');
                        }
                        else
                            vm.appGeneralErrorAlert();

                        if ( error.data.deletedNum )
                            vm.appDeletedNum = error.data.deletedNum;

                        vm.appSearchText = '';
                        vm.appResources = [];
                        vm.fetchingData = false;
                        progress.fail();
                    });
                },
                appDoSearch() {
                    let vm = this;

                    if ( vm.appSearchText.length ) {
                        vm.appResources = [];
                        vm.appPagination = vm.appGetInitialPagination();
                        vm.appSearching = true;
                        if ( typeof vm.fetchResources === 'function' )
                            vm.fetchResources();
                    }
                },
                appQuickEditResources() {
                    let vm = this;
                    let action = _.toLower(vm.appQuickEditOption);
                    let selected = vm.appSelectedResources;
                    let progress = vm.$Progress;

                    if ( action.length && selected.length ) {

                        if ( action === 'export' ) {
                            let urlString = '';

                            _.forEach(selected, function(id, index) {
                                let operand = index ? '&' : '?';
                                urlString += operand + 'resourceIds[]=' + id;
                            });

                            window.location = vm.appResourceUrl + '/export' + urlString;
                        }
                        else {
                            let attachTo = (typeof vm.attachTo === 'undefined') ? 0 : vm.attachTo;

                            progress.start();

                            vm.$http.put(vm.appResourceUrl + '/' + action + '/quick-update', {resources: selected, attachTo: attachTo}).then(function (response) {
                                if (response.data && response.data.success) {
                                    progress.finish();

                                    _.delay(function() {
                                        vm.appQuickEditOption = '';
                                        vm.appCustomSuccessAlertConfirmed(response.data.success);

                                        if ( typeof vm.fetchResources === 'function' ) {
                                            _.delay(function () {
                                                vm.fetchResources();
                                            }, 1000);
                                        }
                                    }, 500);
                                }
                            }, function (error) {
                                if ( error.status && error.status === 403 && error.data )
                                    vm.appCustomErrorAlertConfirmed(error.data.error);
                                else if ( error.status && error.status === 404 && error.data )
                                    vm.appCustomErrorAlert(error.data.error);
                                else
                                    vm.appGeneralErrorAlert();

                                progress.fail();
                                vm.appQuickEditOption = '';
                            });
                        }
                    }
                },
                appExportAll() {
                    let vm = this;
                    let trash = (typeof vm.trash === 'undefined') ? 0 : vm.trash;
                    let belongingTo = (typeof vm.belongingTo === 'undefined') ? 0 : vm.belongingTo;

                    window.location = vm.appResourceUrl + '/export?trash=' + trash + '&belongingTo=' + belongingTo;
                },
                appInitialiseSettings() {
                    let vm = this;

                    if ( vm.appSettingsKey.length && vm.appSettings[vm.appSettingsKey] ) {

                        //Order Attr
                        let order_by = vm.appSettingsKey + '_' + 'order_by';

                        if ( vm.appSettings[vm.appSettingsKey][order_by] && vm.appSettings[vm.appSettingsKey][order_by].length )
                            vm.appOrderAttr = vm.appSettings[vm.appSettingsKey][order_by];

                        //Order
                        let order = vm.appSettingsKey + '_' + 'order';

                        if ( vm.appSettings[vm.appSettingsKey][order] && vm.appSettings[vm.appSettingsKey][order].length )
                            vm.appOrder = vm.appSettings[vm.appSettingsKey][order];

                        //Order Toggle
                        vm.appOrderToggle = _.toLower(vm.appOrder) === 'asc' ? 1 : vm.appOrderToggle;

                        //Per Page
                        let per_page = vm.appSettingsKey + '_' + 'per_page';

                        if ( vm.appSettings[vm.appSettingsKey][per_page] && parseInt(vm.appSettings[vm.appSettingsKey][per_page]) )
                            vm.appPerPage = vm.appSettings[vm.appSettingsKey][per_page];

                        vm.appPagination = vm.appGetInitialPagination();
                    }
                },
                appInitialiseTooltip() {
                    _.delay(function() {
                        $('[data-toggle="tooltip"]').tooltip();
                    }, 1000);
                },
                appGetInitialPagination() {
                    let vm = this;
                    return {
                        total: 0,
                        per_page: vm.appPerPage,
                        current_page: 1,
                        last_page: 0,
                        from: 1,
                        to: vm.appPerPage
                    };
                },
                appChangeSort(attr) {
                    let vm = this;
                    let orderToggle = ( _.toLower(vm.appOrderAttr) === _.toLower(attr) ) ? vm.appOrderToggle * -1 : 1;

                    if ( typeof vm.fetchResources === 'function' )
                        vm.fetchResources(_.toLower(attr), orderToggle);
                },
                appGetSortMarkup(attr) {
                    let vm = this;
                    let html = '';
                    if ( _.toLower(vm.appOrderAttr) === _.toLower(attr) )
                        html = ( vm.appOrderToggle === 1 ) ? '&darr;' : '&uarr;';
                    return html;
                },
                appHidePagination() {
                    let vm = this;
                    return ( _.ceil(vm.appPagination.total / vm.appPagination.per_page) === 1 )
                },
                appActiveMarkup(activeAttr) {
                    return activeAttr ? '&#10003;' : '&#10007;';
                },
                appConsentMarkup(activeAttr) {

                    if ( activeAttr === -1 )
                        return '&hellip;';
                    else if ( activeAttr === 1 )
                        return '&#10003;';

                    return '&#10007;';
                },
                appStartCase(word) {
                    return _.startCase(word);
                },
                appCapitalise(string) {
                    return _.capitalize(string);
                },
                appReplaceUnderscores(string) {
                  return _.replace(string, /_/g, ' ');
                },
                appUpdateSettings() {
                    let vm = this;
                    let win = window;

                    if ( vm.appSettingsKey.length ) {

                        //Order Attr
                        let order_by = vm.appSettingsKey + '_' + 'order_by';
                        if ( win.settings[vm.appSettingsKey] )
                            win.settings[vm.appSettingsKey][order_by] = vm.appOrderAttr;
                        else {
                            win.settings[vm.appSettingsKey] = {};
                            win.settings[vm.appSettingsKey][order_by] = vm.appOrderAttr;
                        }

                        // Order
                        let order = vm.appSettingsKey + '_' + 'order';
                        win.settings[vm.appSettingsKey][order] = vm.appOrder;

                        //Per Page
                        let per_page = vm.appSettingsKey + '_' + 'per_page';
                        win.settings[vm.appSettingsKey][per_page] = vm.appPerPage;
                    }
                },
                appGeneralErrorAlert(time = 3000) {
                    swal({ title: "An Error Occurred", text: 'Please refresh the page and try again', type: 'error', animation: 'slide-from-top', timer: parseInt(time)});
                },
                appCustomErrorAlert(errorMsg, time = 3000) {
                    swal({ title: "Uh oh!", text: errorMsg, type: 'error', animation: 'slide-from-top', timer: parseInt(time)});
                },
                appCustomErrorAlertConfirmed(errorMsg) {
                    swal({ title: "Uh oh!", text: errorMsg, type: 'error', animation: 'slide-from-top'}, function(){});
                },
                appValidationErrorAlert(time = 3000) {
                    swal({ title: "Slight niggle there", text: 'Please check the highlighted fields and try again', type: 'error', animation: 'slide-from-top', timer: parseInt(time)});
                },
                appGeneralSuccessAlert(time = 3000) {
                    swal({ title: "Jolly Good", text: 'You done good!', type: 'success', animation: 'slide-from-bottom', timer: parseInt(time)});
                },
                appCustomSuccessAlert(successMsg, time = 3000) {
                    swal({ title: "Excellent!", text: successMsg, type: 'success', animation: 'slide-from-bottom', timer: parseInt(time)});
                },
                appCustomSuccessAlertConfirmed(successMsg) {
                    swal({ title: "Excellent!", text: successMsg, type: 'success', animation: 'slide-from-bottom'}, function(){});
                },
                appUserHasPermission(action) {
                    let vm = this;

                    switch(action) {
                        case 'activate':
                        case 'deactivate':
                        case 'permissions':
                        case '':
                            action = 'update';
                            break;
                        case 'export':
                            action = 'read';
                            break;
                        case 'destroy':
                        case 'restore':
                            action = 'delete';
                            break;
                    }

                    return vm.appUser.is_super_admin ? true : vm.appPermissions[action + '_' + vm.appPermissionsKey];
                },
                appUserHasPermissionOnUser(action, user) {
                    let permission = false;
                    let vm = this;

                    if ( typeof vm.appUser === 'object' ) {
                        action = ( action === 'permissions' || action === '' ) ? 'update' : action;

                        if ( vm.appUser.id === user.id ) {

                        }
                        else if ( vm.appUser.is_super_admin )
                            permission = true;
                        else if ( ! user.is_super_admin )
                            permission = vm.appPermissions[action + '_' + vm.appPermissionsKey];
                    }

                    return permission;
                },
                appUserIsCurrentUser(user) {
                    return this.appUser.id === user.id;
                },
                appIsTrashPage() {
                    return _.includes(this.$route.name, 'trash');
                },
            },
            watch: {
                appSelectedResources() {
                    this.appQuickEditOption = '';
                },
                appQuickEditOption(action) {
                    let vm = this;
                    let num = vm.appSelectedResources.length;

                    if ( action.length && num ) {
                        if ( action === 'attach' )
                            vm.$emit('attaching');

                        else {
                            let records = num === 1 ? 'record' : 'records';

                            swal({
                                title: _.capitalize(action) + ' ' + num + ' ' + records + '?',
                                type: "warning",
                                showCancelButton: true,
                                confirmButtonText: _.capitalize(action),
                            }, function (confirmed) {
                                if (confirmed && typeof vm.quickEditResources === 'function')
                                    vm.quickEditResources();
                                else
                                    vm.appQuickEditOption = '';
                            });
                        }
                    }
                },
                appSearchText(newVal, oldVal) {
                    let vm = this;

                    if ( oldVal.length && ! newVal.length ) {
                        vm.appSearching = false;

                        if ( typeof vm.fetchResources === 'function' )
                            vm.fetchResources();
                    }
                },
                appPerPage(newVal, oldVal) {
                    let vm = this;

                    if ( newVal !== oldVal ) {
                        vm.appPagination.per_page = newVal;
                    }
                }
            },
            filters: {
                dateToTheMinute(date) {
                    return date ? moment(date + ' Z', 'YYYY-MM-DD HH:mm:ss Z', true).format('D MMM YYYY HH:mm') : '-';
                },
                dateToTheMinWithDayOfWeek(date) {
                    return date ? moment(date + ' Z', 'YYYY-MM-DD HH:mm:ss Z', true).format('llll') : '-';
                },
                dateToTheDay(date) {
                    return date ? moment(date + ' Z', 'YYYY-MM-DD HH:mm:ss Z', true).format('D MMM YYYY') : '-';
                }
            }
        });

        Vue.prototype.$appPlugin = {
            doSomething(vm) {
              return vm.appUser;
            }
        };

    }
};

export default AppListScreenPlugin;