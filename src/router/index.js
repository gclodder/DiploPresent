import { createRouter, createWebHashHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import EditorView from '../views/EditorView.vue'
import PresenterView from '../views/PresenterView.vue'
import LiveView from '../views/LiveView.vue'
import DashboardView from '../views/DashboardView.vue'
import AdminView from '../views/AdminView.vue'

export default createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/editor', name: 'editor', component: EditorView },
    { path: '/presenter', name: 'presenter', component: PresenterView },
    { path: '/beheer', name: 'beheer', component: AdminView },
    { path: '/admin', redirect: '/beheer' },
    { path: '/dashboard/:sessionId', name: 'dashboard', component: DashboardView },
    { path: '/live/:listName?', name: 'live', component: LiveView },
  ],
})
