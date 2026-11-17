import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import './styles/variables.css'
import './styles/base.css'
import './styles/componentes.css'
import './styles/layout.css'
import App from './App.jsx'
import AutenticacionProvider from './context/AutenticacionProvider.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <AutenticacionProvider>
        <App />
      </AutenticacionProvider>
    </BrowserRouter>
  </StrictMode>,
)
