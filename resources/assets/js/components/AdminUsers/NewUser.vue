<template>
    <div class="mt-5">
        <div class="sk-spinner sk-spinner-pulse bg-gray-800" v-if="fetchingData"></div>

        <template v-if="! fetchingData">
            <div v-if="appUserHasPermission('create')">
                <form v-on:submit.prevent='createResource'>
                    <div class="form-group row">
                        <label class="col-md-4 " for="first_name">First Name</label>
                        <div class="col-md-8">
                            <input type="text" class="form-control" id="first_name" v-model.trim="resource.first_name" v-bind:class="validationErrors.first_name ? 'is-invalid' : ''" placeholder="First Name" autofocus>
                            <small class="invalid-feedback">
                                {{ validationErrors.first_name }}
                            </small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-4 " for="last_name">Last Name</label>
                        <div class="col-md-8">
                            <input type="text" class="form-control" id="last_name" v-model.trim="resource.last_name" v-bind:class="validationErrors.last_name ? 'is-invalid' : ''" placeholder="Last Name">
                            <small class="invalid-feedback">
                                {{ validationErrors.last_name }}
                            </small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-4 " for="email">Email</label>
                        <div class="col-md-8">
                            <input type="text" class="form-control" id="email" v-model.trim="resource.email" v-bind:class="validationErrors.email ? 'is-invalid' : ''" placeholder="Email">
                            <small class="invalid-feedback">
                                {{ validationErrors.email }}
                            </small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-4 " for="username">Username</label>
                        <div class="col-md-8">
                            <input type="text" class="form-control" id="username" v-model.trim="resource.username" v-bind:class="validationErrors.username ? 'is-invalid' : ''" placeholder="Username">
                            <small class="invalid-feedback">
                                {{ validationErrors.username }}
                            </small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class='col-md-4 ' for="password">Password</label>
                        <div class="col-md-8">
                            <input type="password" class="form-control" id="password" v-model="resource.password" v-bind:class="validationErrors.password ? 'is-invalid' : ''" placeholder="Password">
                            <small class="invalid-feedback">
                                {{ validationErrors.password }}
                            </small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class='col-md-4 ' for="password_confirmation">Password Confirmation</label>
                        <div class="col-md-8">
                            <input type="password" class="form-control" id="password_confirmation" v-model="resource.password_confirmation" v-bind:class="validationErrors.password_confirmation ? 'is-invalid' : ''" placeholder="Repeat Password">
                            <small class="invalid-feedback">
                                {{ validationErrors.password_confirmation }}
                            </small>
                        </div>
                    </div>
                    <div class="form-group row checkbox">
                        <div class="col-md-8 ml-md-auto">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="active" v-model="resource.active">
                                <label class="custom-control-label" for="active">
                                    Active [Controls whether they can log in]
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-md-8 ml-md-auto">
                            <button type="submit" class="btn btn-info btn-lg">Save</button>
                        </div>
                    </div>
                </form>
            </div>
            <div v-if="! appUserHasPermission('create')">
                <i class="icon ion-alert"></i> {{ appUnauthorisedErrorMessage }}
            </div>
        </template>
    </div>
</template>

<script>
    export default {
        mounted() {
            this.$nextTick(function() {
                this.appGoTime();
            });
        },
        data() {
            return {
                fetchingData: true,
                resource: {first_name: '', last_name: '', username: '', email: '', password: '', password_confirmation: '', active: 1},
                validationErrors: {first_name: '', last_name: '', username: '', email: '', password: '', password_confirmation: ''}
            }
        },
        methods: {
            createResource() {
                this.appCreateResource();
            }
        }
    }
</script>
