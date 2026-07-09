import { createRouter, createWebHistory } from 'vue-router';
import { auth } from './stores/auth';
import LoginView from './views/LoginView.vue';
import CategoriesView from './views/CategoriesView.vue';
import CategoryView from './views/CategoryView.vue';
import CategoryCardsView from './views/CategoryCardsView.vue';
import StudyView from './views/StudyView.vue';
import StepCardsView from './views/StepCardsView.vue';
import ProfileView from './views/ProfileView.vue';
import UsersView from './views/UsersView.vue';
import UserEditView from './views/UserEditView.vue';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        { path: '/', name: 'categories', component: CategoriesView },
        { path: '/categories/:id', name: 'category', component: CategoryView, props: true },
        { path: '/categories/:id/cards', name: 'category-cards', component: CategoryCardsView, props: true },
        { path: '/categories/:id/study/:step', name: 'study', component: StudyView, props: true },
        { path: '/categories/:id/steps/:step/cards', name: 'step-cards', component: StepCardsView, props: true },
        { path: '/profile', name: 'profile', component: ProfileView },
        { path: '/users', name: 'users', component: UsersView, meta: { role: 'manageUser' } },
        { path: '/users/:id/edit', name: 'user-edit', component: UserEditView, meta: { role: 'manageUser' } },
    ],
});

router.beforeEach(async (to) => {
    await auth.ready();

    if (!auth.user && !to.meta.guest) {
        return { name: 'login' };
    }

    if (auth.user && to.meta.guest) {
        return { name: 'categories' };
    }

    if (to.meta.role && !auth.hasRole(to.meta.role)) {
        return { name: 'categories' };
    }
});
