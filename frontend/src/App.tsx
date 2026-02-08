import { useEffect, useState } from "react";

type Flight = {
  flightNumber: number;
  departureAirport: string;
  arrivalAirport: string;
  departureDate: string;
};

const styles = {
  layout: {
    display: "grid",
    gridTemplateColumns: "1fr 1.4fr",
    gap: 16,
    alignItems: "start",
  } as const,
  card: {
    background: "#1b1b1b",
    border: "1px solid #2a2a2a",
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
  } as const,
  stack: {
    display: "flex",
    flexDirection: "column",
    gap: 16,
  } as const,
};


const API = "http://localhost:8001";

export default function App() {
  const [flights, setFlights] = useState<Flight[]>([]);
  const [info, setInfo] = useState<any>(null);

  const [selectedFlight, setSelectedFlight] = useState<number>(101);

  const [bookName, setBookName] = useState("");
  const [bookClass, setBookClass] = useState<"first" | "business" | "economy">("economy");
  const [bookResult, setBookResult] = useState<any>(null);

  const [statusName, setStatusName] = useState("");
  const [statusResult, setStatusResult] = useState<any>(null);
  const [statusError, setStatusError] = useState<string | null>(null);


  const [cancelName, setCancelName] = useState("");
  const [cancelClass, setCancelClass] = useState<"first" | "business" | "economy">("economy");
  const [cancelResult, setCancelResult] = useState<any>(null);

  

  const [error, setError] = useState<string | null>(null);

  async function loadFlights() {
    setError(null);
    const res = await fetch(`${API}/flights`);
    const data = await res.json();
    setFlights(data);
  }

  async function loadFlightInfo() {
    setError(null);
    const res = await fetch(`${API}/flights/${selectedFlight}/info`);
    const data = await res.json();
    setInfo(data);
}


  async function book() {
    setError(null);
    setBookResult(null);

    const body = new URLSearchParams();
    body.set("name", bookName);
    body.set("class", bookClass);

    const res = await fetch(`${API}/flights/101/book`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    });

    const data = await res.json();
    if (!res.ok) setError(JSON.stringify(data));
    else setBookResult(data);

    await loadFlightInfo();
  }

  async function cancel() {
    setError(null);
    setCancelResult(null);

    const body = new URLSearchParams();
    body.set("name", cancelName);
    body.set("class", cancelClass);

    const res = await fetch(`${API}/flights/101/cancel`, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    });

    const data = await res.json();
    if (!res.ok) setError(JSON.stringify(data));
    else setCancelResult(data);

    await loadFlightInfo();
  }

  async function checkStatus() {
    setStatusError(null);
    setStatusResult(null);

    const res = await fetch(`${API}/passengers/status?name=${encodeURIComponent(statusName)}`);
    const data = await res.json();

    if (!res.ok) {
      setStatusError(JSON.stringify(data));
      return;
    }

    setStatusResult(data);
  }

  async function resetDemo() {
  setError(null);

  const res = await fetch(`${API}/reset`, { method: "POST" });
  const data = await res.json();

  if (!res.ok) {
    setError(JSON.stringify(data));
    return;
  }

  // reload UI data
  await loadFlights();
  await loadFlightInfo();

  // clear UI results
  setBookResult(null);
  setCancelResult(null);
  setStatusResult(null);
  setStatusError(null);
}


  useEffect(() => {
    loadFlights();
    loadFlightInfo();
  }, [selectedFlight]);


function renderClass(className: string, classData: any) {
  return (
    <div key={className} style={{ marginBottom: 24 }}>
      <details open={className !== "economy"} style={{ marginBottom: 12 }}>
        <summary style={{ cursor: "pointer", fontWeight: 700 }}>
      {className.toUpperCase()}
        </summary>
  
      

      <table style={{ width: "100%", borderCollapse: "collapse" }}>
        <thead>
          <tr>
            <th style={{ borderBottom: "1px solid #444", textAlign: "left" }}>Seat</th>
            <th style={{ borderBottom: "1px solid #444", textAlign: "left" }}>Passenger</th>
          </tr>
        </thead>
        <tbody>
          {classData.seats.map((seat: any) => (
            <tr key={seat.seatNumber}>
              <td style={{ padding: "6px 0", borderBottom: "1px solid #2a2a2a" }}>
                {seat.seatNumber}
              </td>
              <td style={{ padding: "6px 0", borderBottom: "1px solid #2a2a2a" }}>
                {seat.passenger ?? <i style={{ opacity: 0.6 }}>empty</i>}
              </td>
            </tr>
          ))}
      </tbody>
      </table>
      </details>
      {classData.waitlist.length > 0 && (
        <p style={{ marginTop: 8 }}>
          <b>Waitlist:</b>{" "}
          {classData.waitlist.length > 0
            ? classData.waitlist.join(", ")
            : <i style={{ opacity: 0.6 }}>none</i>}
        </p>

      )}
    </div>
  );
}



  return (
  <div style={{ fontFamily: "system-ui", padding: 16, maxWidth: 1100, margin: "0 auto" }}>
    <h1>Flight Scheduler</h1>
    <p>Simple full-stack demo (React + TS frontend, PHP backend).</p>
    <div style={{ marginBottom: 16, display: "flex", gap: 8 }}>
    <button onClick={resetDemo}>Reset demo data</button>
    </div>

    {error && (
      <div style={{ padding: 12, background: "#ffe5e5", marginBottom: 12, borderRadius: 8 }}>
        <b>Error:</b> <pre style={{ margin: 0, whiteSpace: "pre-wrap" }}>{error}</pre>
      </div>
    )}

    <div style={styles.layout}>
      {/* LEFT COLUMN (actions) */}
      <div style={{...styles.stack, position: "sticky", top: 16 }}>

        <section style={styles.card}>
          <h2>Selected flight</h2>
          <select
            value={selectedFlight}
            onChange={(e) => setSelectedFlight(Number(e.target.value))}
          >
            {flights.map((f) => (
              <option key={f.flightNumber} value={f.flightNumber}>
                {f.flightNumber} {f.departureAirport} → {f.arrivalAirport} ({f.departureDate})
              </option>
            ))}
          </select>
        </section>


        <section style={styles.card}>
          <h2>Book a seat (Flight 101)</h2>
          <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
            <input
              placeholder="Passenger name"
              value={bookName}
              onChange={(e) => setBookName(e.target.value)}
            />
            <select value={bookClass} onChange={(e) => setBookClass(e.target.value as any)}>
              <option value="first">first</option>
              <option value="business">business</option>
              <option value="economy">economy</option>
            </select>
            <button onClick={book} disabled={!bookName.trim()}>
              Book
            </button>
          </div>

          {bookResult && (
            <pre style={{ background: "#f5f5f5", padding: 12, borderRadius: 8 }}>
              {JSON.stringify(bookResult, null, 2)}
            </pre>
          )}
        </section>

        <section style={styles.card}>
          <h2>Cancel a booking (Flight 101)</h2>
          <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
            <input
              placeholder="Passenger name"
              value={cancelName}
              onChange={(e) => setCancelName(e.target.value)}
            />
            <select value={cancelClass} onChange={(e) => setCancelClass(e.target.value as any)}>
              <option value="first">first</option>
              <option value="business">business</option>
              <option value="economy">economy</option>
            </select>
            <button onClick={cancel} disabled={!cancelName.trim()}>
              Cancel
            </button>
          </div>

          {cancelResult && (
            <pre style={{ background: "#f5f5f5", padding: 12, borderRadius: 8 }}>
              {JSON.stringify(cancelResult, null, 2)}
            </pre>
          )}
        </section>

        <section style={styles.card}>
          <h2>Passenger status</h2>

          <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap" }}>
            <input
              placeholder="Passenger name"
              value={statusName}
              onChange={(e) => setStatusName(e.target.value)}
            />
            <button onClick={checkStatus} disabled={!statusName.trim()}>
              Check
            </button>
          </div>

          {statusError && (
            <pre style={{ background: "#ffe5e5", padding: 12, borderRadius: 8, whiteSpace: "pre-wrap" }}>
              {statusError}
            </pre>
          )}

          {statusResult && (
            <pre style={{ background: "#f5f5f5", padding: 12, borderRadius: 8 }}>
              {JSON.stringify(statusResult, null, 2)}
            </pre>
          )}
        </section>
      </div>

      {/* RIGHT COLUMN (data) */}
      <div style={styles.stack}>
        <section style={styles.card}>
          <h2>Flights</h2>
          <ul>
            {flights.map((f) => (
              <li key={f.flightNumber}>
                <b>{f.flightNumber}</b> {f.departureAirport} → {f.arrivalAirport} ({f.departureDate})
              </li>
            ))}
          </ul>
        </section>

        <section style={styles.card}>
          <h2 style={{ display: "flex", gap: 12, alignItems: "center", justifyContent: "space-between" }}>
            <span>Flight 101 info</span>
            <button onClick={loadFlightInfo} style={{ padding: "6px 10px", fontSize: 14 }}>
                Refresh
            </button>
          </h2>

          {info?.classes && (
            <>
              {Object.entries(info.classes).map(([className, classData]) =>
                renderClass(className, classData)
              )}
            </>
          )}
        </section>
      </div>
    </div>
  </div>
);
}
