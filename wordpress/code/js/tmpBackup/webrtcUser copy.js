const localVideo = document.getElementById("localVideo");
const remoteVideo = document.getElementById("remoteVideo");
let params = new URLSearchParams(window.location.search);
let user_guid = params.get("user_guid"); // This gets '1' from your example URL
let room_guid = params.get("room_guid");
let user_type = params.get("user_type");
let localStream;
let peerConnection;
let userStartChat = 0;
const config = {
  iceServers: [{ urls: "stun:stun.l.google.com:19302" }], // Google's public STUN server
};
// Get local media stream


const socket = new WebSocket("wss://cam.afikim.pro:8080");

socket.addEventListener("open", function (event) {
  // Send identification message
  if (userStartChat == "1") {
    socket.send(
      JSON.stringify({
        type: "identify", user_guid: user_guid, room_guid: room_guid,   user_type: user_type})
    );
  }
});

// Handle WebSocket messages
socket.addEventListener("message", async function (event) {
  if (userStartChat == "1") {
    const data = JSON.parse(event.data);
    if (typeof data.error != "undefined" && data.error === "sessionSts1") {
      alert("מפגש זה הסתיים ולא מחוייב יותר . עליך ליזום מפגש חדש");
      window.location.href = "/";
    }
    if (data.type === "offer") {
      await createPeerConnection();
      await peerConnection.setRemoteDescription(
        new RTCSessionDescription(data.data)
      );
      const answer = await peerConnection.createAnswer();
      await peerConnection.setLocalDescription(answer);
      socket.send(
        JSON.stringify({
          type: "answer",
          user_guid: user_guid,
          room_guid: room_guid,
          user_type: user_type,
          data: answer,
        })
      );
      
    } 
    else if (data.type === "answer") {
      await peerConnection.setRemoteDescription(
        new RTCSessionDescription(data.data)
      );
    } else if (data.type === "candidate") {
      await peerConnection.addIceCandidate(new RTCIceCandidate(data.data));
    }
  }
});


async function createPeerConnection(localStream) {
  peerConnection = new RTCPeerConnection(config);
  //Get local media stream

  // Add local tracks to peer connection
  localStream.getTracks().forEach(track => {
    peerConnection.addTrack(track, localStream);
  });



  // Listen for remote tracks
  peerConnection.ontrack = event => {
    const [remoteStream] = event.streams;
    const remoteVideo = document.getElementById('remoteVideo');
    remoteVideo.srcObject = remoteStream;
    // Check the types of tracks in the stream
    let hasAudio = event.streams[0].getAudioTracks().length > 0;
    let hasVideo = event.streams[0].getVideoTracks().length > 0;

    console.log("Received stream has audio:", hasAudio);
    console.log("Received stream has video:", hasVideo);

    // You can use hasAudio and hasVideo to adjust UI or handle the stream accordingly
    if (!hasVideo) {
      console.log("The stream contains only audio.");
      // Handle audio-only stream scenario
    } else if (!hasAudio) {
      console.log("The stream contains only video.");
      // Handle video-only stream scenario
    } else {
      console.log("The stream contains both audio and video.");
      // Handle scenario where both audio and video are present
    }
  };
  // ICE candidate event: send any ICE candidates to the remote peer
  peerConnection.onicecandidate = function (event) {
    if (event.candidate) {

      socket.send(
        JSON.stringify({
          type: "candidate",
          user_guid: user_guid,
          room_guid: room_guid,
          user_type: user_type,
          data: event.candidate,
        })
      );

    }
  };
  
}

// Starting a call (example)


async function startCall(num) {
  const stream = await navigator.mediaDevices.getUserMedia({audio: true, video: true});
  console.log('Received local stream');
  localVideo.srcObject = stream;
  localStream = stream;
  userStartChat = 1;
  await createPeerConnection(stream);
  const offer = await peerConnection.createOffer();
  await peerConnection.setLocalDescription(offer);
  socket.send(
    JSON.stringify({
      type: "offer",
      user_guid: user_guid,
      room_guid: room_guid,
      user_type: user_type,
      data: offer,
    })
  );

}


function disconnect() {
  socket.send(
    JSON.stringify({
      type: "disconnect",
      user_guid: user_guid,
      room_guid: room_guid,
      user_type: user_type,
      data: "disconnect",
    })
  );
  console.log("Function called by worker at", new Date());
}
function update_time_use() {
  if (userStartChat == 1) {
    socket.send(
      JSON.stringify({
        type: "update",
        user_guid: user_guid,
        room_guid: room_guid,
        user_type: user_type,
        data: "update",
      })
    );
    //console.log("Function called by worker at", new Date());
  }
}
async function getLocalStream(num) {
  try {
    if (num == 1)   
            localStream = await navigator.mediaDevices.getUserMedia({video: true,audio: true  });
    else
            localStream = await navigator.mediaDevices.getUserMedia({video: false,audio: true });
    localVideo.srcObject = localStream;
  } catch (error) {
    alert("לא ניתן לגשת למצלמה או למיקרופון, אנא נסה לפתוח את האתר מחדש");
    return;
  }
}
setInterval(update_time_use, 3000); // Call doSomething every 3 seconds