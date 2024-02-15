export default {
  path: '/resources/usergroups',
  meta: { requiresAuth: true },
  name: 'usergroups',
  component: () => import('../components/usergroup/Layout.vue'),
  redirect: { name: 'UserGroupList' },
  children: [
    {
      name: 'UserGroupList',
      path: '',
      component: () => import('../views/usergroup/List.vue')
    },
    {
      name: 'UserGroupShow',
      path: 'show/:group_id?',
      component: () => import('../views/usergroup/Show.vue'),
      props: true
    },
    {
      name: 'UserGroupSearch',
      path: 'search',
      component: () => import('../views/usergroup/Search.vue'),
      props: (route) => ({ q: route.query.q })
    },
    {
      name: 'UserGroupInvite',
      path: 'invite/:group_id?',
      component: () => import('../views/usergroup/Invite.vue'),
      props: true
    }
  ]
};
