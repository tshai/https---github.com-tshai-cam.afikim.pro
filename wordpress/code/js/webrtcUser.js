const localVideo = document.getElementById("localVideo");
const remoteVideo = document.getElementById("remoteVideo");
const params = new URLSearchParams(window.location.search);
const user_guid = params.get("user_guid"); // This gets '1' from your example URL
const room_guid = params.get("room_guid");
const user_type = params.get("user_type");
let localStream;
let peerConnection;
let userStartChat = 0;
let audio;
let video;
const config = {
  iceServers: [{ urls: "stun:stun.l.google.com:19302" }], // Google's public STUN server
};

// Initialize WebSocket connection
const socket = new WebSocket("wss://cam.afikim.pro:8080");

$(document).ready(function () {
  if (user_type == "manager") {
    navigator.mediaDevices
      .getUserMedia({ video: true, audio: true })
      .then(function (localStream) {
        // Assuming localVideo is a previously selected DOM element
        localVideo.srcObject = localStream;
        $("#startCall").show();
      })
      .catch(function (error) {
        alert("לא ניתן לגשת למצלמה או למיקרופון, אנא נסה לפתוח את האתר מחדש");
        console.error("getUserMedia error:", error);
      });
  }
});

socket.addEventListener("open", function (event) {
  // Send identification message
    socket.send(
      JSON.stringify({
        type: "identify",
        user_guid: user_guid,
        room_guid: room_guid,
        user_type: user_type,
      })
    );
});

// Handle WebSocket messages
socket.addEventListener("message", async function (event) {
  // if (userStartChat == "1") {
    const data = JSON.parse(event.data);
    if (typeof data.error != "undefined" && data.error === "sessionSts1") {
      alert("מפגש זה הסתיים ולא מחוייב יותר . עליך ליזום מפגש חדש");
      window.location.href = "/";

      
    } else if (data.type === "answer") {
      await peerConnection.setRemoteDescription(
        new RTCSessionDescription(data.data)
      );
    }
    else if (data.type === "offer") {
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
      //socket.send(JSON.stringify({type: 'answer', answer: answer}));
    } 
    else if (data.type === "candidate") {
      await peerConnection.addIceCandidate(new RTCIceCandidate(data.data));
    }
  // }
});

async function createPeerConnection(num) {
  peerConnection = new RTCPeerConnection(config);

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
      //socket.send(JSON.stringify({type: 'candidate', candidate: event.candidate}));
    }
  };

  // Stream event: set the remote video source when a stream is received from the remote peer
  // peerConnection.ontrack = function (event) {
  //     remoteVideo.srcObject = event.streams[0];
  // };
  peerConnection.ontrack = function (event) {
    // Set the remote video source when a stream is received from the remote peer
    remoteVideo.srcObject = event.streams[0];

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
  //Get local media stream

  //with camera

  try {
      localStream = await navigator.mediaDevices.getUserMedia({
        video: video,
        audio: audio,
      });
 
    localVideo.srcObject = localStream;
  } catch (error) {
    alert("לא ניתן לגשת למצלמה או למיקרופון, אנא נסה לפתוח את האתר מחדש");
    console.error("getUserMedia error:", error);
    return;
  }

  // Add each track from the local stream to the peer connection
  localStream.getTracks().forEach((track) => {
    peerConnection.addTrack(track, localStream);
  });
}

// Starting a call (example)
async function startCall(num) {
  if(num==1){
    video=true;
    audio=true;
    }
    else{
      video=false;
      audio=true;
    }
  socket.send(
    JSON.stringify({
      type: "startCall",
      user_guid: user_guid,
      room_guid: room_guid,
      user_type: user_type,
      data: "startCall",
    })
  );
}
