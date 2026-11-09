import { useState } from 'react'

import './App.css'
import { api } from './api/client'
import { Login, type Ator } from './pages/Login'

function App() {
  const [ator, setAtor] = useState<Ator | null>(null)

  async function sair() {
    await api.POST('/auth/logout', {
      headers: { Authorization: `Bearer ${sessionStorage.getItem('ebi.token') ?? ''}` },
    })
    sessionStorage.removeItem('ebi.token')
    setAtor(null)
  }

  return (
    <>
      {ator ? (
        <main>
          <h1>Bem-vinda</h1>
          <p data-testid="ator-nome">{ator.nome}</p>
          <p>{ator.email}</p>
          <p>{ator.papel}</p>
          <button type="button" onClick={sair}>
            Sair
          </button>
        </main>
      ) : (
        <Login onEntrar={setAtor} />
      )}
    </>
  )
}

export default App