import React from 'react'
import ReactDOM from 'react-dom/client'
import { RouterProvider, createRouter } from '@tanstack/react-router'
import './index.css'
import { Agentation } from 'agentation'

// Import the generated route tree
import { routeTree } from './routeTree.gen'

// Determine if we're in SSG mode
const isSsg = import.meta.env.SSG === true || window.__TSR_SSG__ === true

// Create a new router instance
const router = createRouter({ 
  routeTree,
  defaultPreload: 'intent',
  scrollRestoration: true,
  // Enable SSG for static generation
  ssg: isSsg,
})

// Render the app
const rootElement = document.getElementById('root')
if (rootElement && !rootElement.innerHTML) {
  const root = ReactDOM.createRoot(rootElement)
  root.render(
    <React.StrictMode>
      <RouterProvider router={router} />
      {import.meta.env.DEV && <Agentation />}
    </React.StrictMode>,
  )
}
