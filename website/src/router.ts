import { routeTree } from './routeTree.gen'
import { createRouter } from '@tanstack/react-router'

// Create a new router instance
const router = createRouter({ 
  routeTree,
  defaultPreload: 'intent',
  scrollRestoration: true,
})

// Register the router instance for type safety
declare module '@tanstack/react-router' {
  interface Register {
    router: typeof router
  }
}
